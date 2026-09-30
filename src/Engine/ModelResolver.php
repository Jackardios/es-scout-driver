<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @internal */
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

    /** @param class-string<Model> $modelClass */
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

    public function createResolver(): Closure
    {
        return function (string $indexName, string $documentId): ?Model {
            return $this->resolve($indexName, $documentId);
        };
    }

    public function resolve(string $indexName, string $documentId): ?Model
    {
        $resolvedIndex = $this->aliasRegistry->resolve($indexName);

        if (!isset($this->indices[$resolvedIndex])) {
            return null;
        }

        $this->ensureModelsLoaded($resolvedIndex);

        return $this->cache[$resolvedIndex][$documentId] ?? null;
    }

    private function ensureModelsLoaded(string $indexName): void
    {
        if (isset($this->cache[$indexName])) {
            return;
        }

        $this->collectAllIds();
        $this->loadModelsForIndex($indexName);
    }

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

        $query->whereIn($model->qualifyColumn($model->getScoutKeyName()), $documentIds);

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
