<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ModelResolver
{
    /** @var array<string, IndexConfig> */
    private array $indices = [];

    /** @var array<string, list<string>> */
    private array $pendingIds = [];

    /** @var array<string, array<string, Model>> */
    private array $cache = [];

    private bool $idsCollected = false;

    public function __construct(
        private readonly AliasRegistry $aliasRegistry,
        private readonly array $rawHits = [],
        private readonly array $rawSuggestions = [],
        private readonly array $rawResult = [],
    ) {}

    /**
     * Register an index configuration.
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

    private function collectIdsFromHits(array $rawHits): void
    {
        foreach ($rawHits as $rawHit) {
            $this->addPendingId($rawHit['_index'] ?? '', $rawHit['_id'] ?? '');

            // Collect from inner_hits recursively
            foreach ($rawHit['inner_hits'] ?? [] as $innerHitsGroup) {
                $this->collectIdsFromHits($innerHitsGroup['hits']['hits'] ?? []);
            }
        }
    }

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

        // Build keyed array for O(1) lookup
        $keyed = [];
        foreach ($collection as $model) {
            $keyed[(string) $model->getScoutKey()] = $model;
        }

        $this->cache[$indexName] = $keyed;
    }

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
