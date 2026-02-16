<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ModelResolver
{
    use ExtractsHitMetadata;
    /** @var array<string, IndexConfig> */
    private array $indices = [];

    /** @var array<string, list<string>> */
    private array $pendingIds = [];

    /** @var array<string, array<string, Model>> */
    private array $cache = [];

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $hitMetadata = [];

    private bool $idsCollected = false;

    /**
     * @param list<array<string, mixed>> $rawHits
     * @param array<string, list<array<string, mixed>>> $rawSuggestions
     * @param array<string, mixed> $rawResult
     */
    public function __construct(
        private readonly AliasRegistry $aliasRegistry,
        private readonly array $rawHits = [],
        private readonly array $rawSuggestions = [],
        private readonly array $rawResult = [],
    ) {}

    /**
     * Register an index configuration.
     *
     * @param class-string<Model> $modelClass
     */
    public function registerIndex(
        string $indexName,
        string $modelClass,
        array $relations = [],
        array $queryCallbacks = [],
        array $collectionCallbacks = [],
        bool $withTrashed = false,
    ): self {
        $this->indices[$indexName] = new IndexConfig(
            modelClass: $modelClass,
            relations: $relations,
            queryCallbacks: $queryCallbacks,
            collectionCallbacks: $collectionCallbacks,
            withTrashed: $withTrashed,
        );

        $this->aliasRegistry->registerIndex($indexName);

        return $this;
    }

    /**
     * Create a new resolver with different raw data but same configuration.
     *
     * @param list<array<string, mixed>> $rawHits
     * @param array<string, list<array<string, mixed>>> $rawSuggestions
     * @param array<string, mixed> $rawResult
     */
    public function withRawData(array $rawHits, array $rawSuggestions = [], array $rawResult = []): self
    {
        $resolver = new self($this->aliasRegistry, $rawHits, $rawSuggestions, $rawResult);
        $resolver->indices = $this->indices;

        return $resolver;
    }

    /**
     * Create a resolver closure for Hit objects.
     */
    public function createResolver(): Closure
    {
        return function (string $indexName, string $documentId): ?Model {
            return $this->resolve($indexName, $documentId);
        };
    }

    /**
     * Resolve a model by index name and document ID.
     */
    public function resolve(string $indexName, string $documentId): ?Model
    {
        $resolvedIndex = $this->aliasRegistry->resolve($indexName);

        if (!isset($this->indices[$resolvedIndex])) {
            return null;
        }

        $this->ensureModelsLoaded($resolvedIndex);

        return $this->cache[$resolvedIndex][$documentId] ?? null;
    }

    /**
     * Preload all models for all indices.
     * Call this if you know you'll need all models.
     */
    public function preloadAll(): void
    {
        $this->collectAllIds();

        foreach (array_keys($this->pendingIds) as $indexName) {
            $this->loadModelsForIndex($indexName);
        }
    }

    /**
     * Get all cached models for an index.
     *
     * @return array<string, Model>
     */
    public function getCachedModels(string $indexName): array
    {
        $resolvedIndex = $this->aliasRegistry->resolve($indexName);

        return $this->cache[$resolvedIndex] ?? [];
    }

    private function ensureModelsLoaded(string $indexName): void
    {
        if (isset($this->cache[$indexName])) {
            return;
        }

        $this->collectAllIds();
        $this->loadModelsForIndex($indexName);
    }

    /**
     * Collect all document IDs from raw hits and suggestions in one pass.
     */
    private function collectAllIds(): void
    {
        if ($this->idsCollected) {
            return;
        }

        $this->collectIdsFromHits($this->rawHits);
        $this->collectIdsFromSuggestions($this->rawSuggestions);

        $this->idsCollected = true;
    }

    /** @param list<array<string, mixed>> $rawHits */
    private function collectIdsFromHits(array $rawHits): void
    {
        foreach ($rawHits as $rawHit) {
            $indexName = $rawHit['_index'] ?? '';
            $documentId = $rawHit['_id'] ?? '';

            $this->addPendingId($indexName, $documentId);

            if ($indexName !== '' && $documentId !== '') {
                $resolvedIndex = $this->aliasRegistry->resolve($indexName);
                if (isset($this->indices[$resolvedIndex])) {
                    $this->hitMetadata[$resolvedIndex][$documentId] = $this->extractHitMetadata($rawHit);
                }
            }

            foreach ($rawHit['inner_hits'] ?? [] as $innerHitsGroup) {
                $this->collectIdsFromHits($innerHitsGroup['hits']['hits'] ?? []);
            }
        }
    }

    /** @param array<string, list<array<string, mixed>>> $rawSuggestions */
    private function collectIdsFromSuggestions(array $rawSuggestions): void
    {
        foreach ($rawSuggestions as $suggestionEntries) {
            foreach ($suggestionEntries as $entry) {
                foreach ($entry['options'] ?? [] as $option) {
                    if (isset($option['_index'], $option['_id'])) {
                        $this->addPendingId($option['_index'], $option['_id']);
                    }
                }
            }
        }
    }

    private function addPendingId(string $indexName, string $documentId): void
    {
        if ($indexName === '' || $documentId === '') {
            return;
        }

        $resolvedIndex = $this->aliasRegistry->resolve($indexName);

        if (!isset($this->indices[$resolvedIndex])) {
            return;
        }

        $this->pendingIds[$resolvedIndex][] = $documentId;
    }

    private function loadModelsForIndex(string $indexName): void
    {
        if (isset($this->cache[$indexName])) {
            return;
        }

        $documentIds = array_unique($this->pendingIds[$indexName] ?? []);

        if ($documentIds === []) {
            $this->cache[$indexName] = [];
            return;
        }

        $config = $this->indices[$indexName];
        $collection = $this->queryModels($config, $documentIds);
        $hitMetadata = $this->hitMetadata[$indexName] ?? [];

        $keyed = [];
        foreach ($collection as $model) {
            $documentId = (string) $model->getScoutKey();
            foreach ($hitMetadata[$documentId] ?? [] as $key => $value) {
                $model->withScoutMetadata($key, $value);
            }
            $keyed[$documentId] = $model;
        }

        $this->cache[$indexName] = $keyed;
    }

    /** @param array<int, string> $documentIds */
    private function queryModels(IndexConfig $config, array $documentIds): EloquentCollection
    {
        /** @var Model $model */
        $model = new $config->modelClass();

        $query = $this->shouldIncludeTrashed($model, $config)
            ? $model->withTrashed()
            : $model->newQuery();

        $query->whereIn($model->getScoutKeyName(), $documentIds);

        if ($config->relations !== []) {
            $query->with($config->relations);
        }

        foreach ($config->queryCallbacks as $callback) {
            $callback($query, $this->rawResult);
        }

        $collection = $query->get();

        foreach ($config->collectionCallbacks as $callback) {
            $collection = $callback($collection);
        }

        return $collection;
    }

    private function shouldIncludeTrashed(Model $model, IndexConfig $config): bool
    {
        return $config->withTrashed
            && in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
