<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as ScoutEngine;

class Engine extends ScoutEngine implements EngineInterface
{
    use HandlesBulkResponse;

    public function __construct(
        protected Client $client,
        protected bool $refreshDocuments = false,
        protected ?string $connectionName = null,
        private readonly ConnectionOperationRouter $connectionRouter = new ConnectionOperationRouter(),
    ) {}

    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        foreach ($this->groupModelsByConnection($models) as $connection => $connectionModels) {
            $this->loadSearchableRelations($connectionModels);

            $body = [];

            foreach ($connectionModels as $model) {
                $searchableData = $model->toSearchableArray();

                if (empty($searchableData)) {
                    continue;
                }

                $metadata = $this->buildDocumentMetadata($model);
                $body[] = ['index' => $metadata];

                if ($this->usesSoftDelete($model) && config('scout.soft_delete', false)) {
                    $searchableData['__soft_deleted'] = $model->trashed() ? 1 : 0;
                }

                $body[] = $searchableData;
            }

            if ($body === []) {
                continue;
            }

            $params = ['body' => $body];

            if ($this->refreshDocuments) {
                $params['refresh'] = 'true';
            }

            $response = $this->resolveClientForConnection($connection)->bulk($params);
            $this->handleBulkResponse($response->asArray());
        }
    }

    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        foreach ($this->groupModelsByConnection($models) as $connection => $connectionModels) {
            $body = [];

            foreach ($connectionModels as $model) {
                $metadata = $this->buildDocumentMetadata($model);
                $body[] = ['delete' => $metadata];
            }

            if ($body === []) {
                continue;
            }

            $params = ['body' => $body];

            if ($this->refreshDocuments) {
                $params['refresh'] = 'true';
            }

            $response = $this->resolveClientForConnection($connection)->bulk($params);
            $this->handleBulkResponse($response->asArray());
        }
    }

    public function search(Builder $builder): array
    {
        return $this->performSearch($builder);
    }

    public function paginate(Builder $builder, $perPage, $page): array
    {
        return $this->performSearch($builder, [
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
        ]);
    }

    public function mapIds($results): Collection
    {
        if (!isset($results['hits']['hits'])) {
            return Collection::make();
        }

        return Collection::make($results['hits']['hits'])
            ->pluck('_id')
            ->values();
    }

    public function map(Builder $builder, $results, $model): EloquentCollection
    {
        $extracted = $this->extractIdsFromResults($results);
        if ($extracted === null) {
            return EloquentCollection::make();
        }

        $models = $model->getScoutModelsByIds($builder, $extracted['ids'])
            ->filter(fn($model) => isset($extracted['positions'][$model->getScoutKey()]))
            ->sortBy(fn($model) => $extracted['positions'][$model->getScoutKey()])
            ->values();

        return new EloquentCollection($models);
    }

    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        $extracted = $this->extractIdsFromResults($results);
        if ($extracted === null) {
            return LazyCollection::make();
        }

        return $model->queryScoutModelsByIds($builder, $extracted['ids'])
            ->cursor()
            ->filter(fn($model) => isset($extracted['positions'][$model->getScoutKey()]))
            ->sortBy(fn($model) => $extracted['positions'][$model->getScoutKey()])
            ->values();
    }

    public function getTotalCount($results): int
    {
        return $results['hits']['total']['value'] ?? 0;
    }

    /**
     * @param array<string, mixed> $results
     * @return array{ids: list<string>, positions: array<string, int>}|null
     */
    private function extractIdsFromResults(array $results): ?array
    {
        if (!isset($results['hits']['hits']) || count($results['hits']['hits']) === 0) {
            return null;
        }

        $ids = Collection::make($results['hits']['hits'])->pluck('_id')->values()->all();

        return [
            'ids' => $ids,
            'positions' => array_flip($ids),
        ];
    }

    public function flush($model): void
    {
        $params = [
            'index' => $model->searchableAs(),
            'body' => ['query' => ['match_all' => new \stdClass()]],
        ];

        if ($this->refreshDocuments) {
            $params['refresh'] = true;
        }

        $this->client->deleteByQuery($params);
    }

    public function createIndex($name, array $options = []): void
    {
        $params = ['index' => $name];

        if (!empty($options)) {
            $params['body'] = $options;
        }

        $this->client->indices()->create($params);
    }

    public function deleteIndex($name): void
    {
        $this->client->indices()->delete(['index' => $name]);
    }

    public function searchRaw(array $params): array
    {
        /** @var ElasticsearchResponse $response */
        $response = $this->client->search($params);
        return $response->asArray();
    }

    public function connection(string $connection): static
    {
        $clone = clone $this;
        $clone->client = app("elastic.client.connection.$connection");
        $clone->connectionName = $connection;
        return $clone;
    }

    public function openPointInTime(string $indexName, ?string $keepAlive = null): string
    {
        $params = [
            'index' => $indexName,
            'keep_alive' => $keepAlive ?? '5m',
        ];

        /** @var ElasticsearchResponse $response */
        $response = $this->client->openPointInTime($params);
        return $response->asArray()['id'];
    }

    public function closePointInTime(string $pointInTimeId): void
    {
        $this->client->closePointInTime(['body' => ['id' => $pointInTimeId]]);
    }

    public function countRaw(array $params): int
    {
        /** @var ElasticsearchResponse $response */
        $response = $this->client->count($params);
        return $response->asArray()['count'] ?? 0;
    }

    public function deleteByQueryRaw(array $params): array
    {
        if ($this->refreshDocuments && !isset($params['refresh'])) {
            $params['refresh'] = true;
        }

        /** @var ElasticsearchResponse $response */
        $response = $this->client->deleteByQuery($params);
        return $response->asArray();
    }

    public function updateByQueryRaw(array $params): array
    {
        if ($this->refreshDocuments && !isset($params['refresh'])) {
            $params['refresh'] = true;
        }

        /** @var ElasticsearchResponse $response */
        $response = $this->client->updateByQuery($params);
        return $response->asArray();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    protected function performSearch(Builder $builder, array $options = []): array
    {
        $params = [
            'index' => $builder->index ?? $builder->model->searchableAs(),
            'body' => [],
        ];

        if ($builder->query !== null && $builder->query !== '') {
            $queryType = $this->getScoutQueryType();
            $params['body']['query'] = [
                $queryType => ['query' => $builder->query],
            ];
        }

        $filters = $this->buildFiltersFromBuilder($builder);
        if ($filters !== []) {
            if (isset($params['body']['query'])) {
                $params['body']['query'] = [
                    'bool' => [
                        'must' => [$params['body']['query']],
                        'filter' => $filters,
                    ],
                ];
            } else {
                $params['body']['query'] = [
                    'bool' => ['filter' => $filters],
                ];
            }
        }

        if (!empty($builder->orders)) {
            $params['body']['sort'] = array_map(
                fn($order) => [$order['column'] => $order['direction']],
                $builder->orders,
            );
        }

        if ($builder->limit !== null) {
            $params['body']['size'] = $builder->limit;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        $params = $this->mergeScoutOptions($params, $builder->options);

        // Fallback to match_all if no query was specified
        if (!isset($params['body']['query'])) {
            $params['body']['query'] = ['match_all' => new \stdClass()];
        }

        if ($builder->callback !== null) {
            return ($builder->callback)($this->client, $builder->query, $params);
        }

        /** @var ElasticsearchResponse $response */
        $response = $this->client->search($params);
        return $response->asArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFiltersFromBuilder(Builder $builder): array
    {
        $filters = [];

        foreach ($builder->wheres as $field => $value) {
            $filters[] = ['term' => [$field => ['value' => $value]]];
        }

        foreach ($builder->whereIns as $field => $values) {
            if ($values === []) {
                continue;
            }

            $filters[] = ['terms' => [$field => array_values($values)]];
        }

        foreach ($builder->whereNotIns as $field => $values) {
            if ($values === []) {
                continue;
            }

            $filters[] = [
                'bool' => [
                    'must_not' => [
                        ['terms' => [$field => array_values($values)]],
                    ],
                ],
            ];
        }

        return $filters;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $scoutOptions
     * @return array<string, mixed>
     */
    private function mergeScoutOptions(array $params, array $scoutOptions): array
    {
        if ($scoutOptions === []) {
            return $params;
        }

        if (array_key_exists('body', $scoutOptions)) {
            if (!is_array($scoutOptions['body'])) {
                throw new InvalidArgumentException('Scout options [body] must be an array.');
            }

            $params['body'] = $this->mergeRequestBody($params['body'], $scoutOptions['body']);
            unset($scoutOptions['body']);
        }

        return array_replace($params, $scoutOptions);
    }

    /**
     * Merge Elasticsearch request body safely.
     *
     * Arrays with list semantics are replaced to avoid invalid structures.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function mergeRequestBody(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                !array_key_exists($key, $base)
                || !is_array($base[$key])
                || !is_array($value)
                || $key === 'query'
                || array_is_list($base[$key])
                || array_is_list($value)
            ) {
                $base[$key] = $value;
                continue;
            }

            $base[$key] = $this->mergeRequestBody($base[$key], $value);
        }

        return $base;
    }

    /**
     * @param iterable<int, mixed> $models
     * @return array<string, array<int, Model>>
     */
    private function groupModelsByConnection(iterable $models): array
    {
        $grouped = [];

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                throw new InvalidArgumentException('Bulk operations expect an iterable of Eloquent models.');
            }

            $connection = $this->connectionRouter->normalize($this->resolveConnectionNameForModel($model));
            $grouped[$connection][] = $model;
        }

        return $grouped;
    }

    /**
     * @param array<int, Model> $models
     */
    private function loadSearchableRelations(array $models): void
    {
        $groups = [];

        foreach ($models as $model) {
            $searchableWith = $model->searchableWith();
            if ($searchableWith === null) {
                continue;
            }

            $relations = is_array($searchableWith) ? $searchableWith : [$searchableWith];
            $relations = array_values(array_filter(
                $relations,
                static fn($relation) => is_string($relation) && $relation !== '',
            ));

            if ($relations === []) {
                continue;
            }

            sort($relations);

            $groupKey = get_class($model) . '|' . implode(',', $relations);
            $groups[$groupKey]['relations'] = $relations;
            $groups[$groupKey]['models'][] = $model;
        }

        foreach ($groups as $group) {
            (new EloquentCollection($group['models']))->loadMissing($group['relations']);
        }
    }

    private function resolveConnectionNameForModel(Model $model): ?string
    {
        $connection = $model->searchableConnection();
        if ($connection !== null && $connection !== '') {
            return $connection;
        }

        if ($this->connectionName !== null && $this->connectionName !== '') {
            return $this->connectionName;
        }

        return null;
    }

    private function resolveClientForConnection(string $connection): mixed
    {
        return $this->connectionRouter->resolveClientForConnection(
            connection: $connection,
            defaultClient: $this->client,
            defaultConnectionName: $this->connectionName,
        );
    }

    private function getScoutQueryType(): string
    {
        if (!function_exists('app') || !app()->bound('config')) {
            return 'simple_query_string';
        }

        return config('elastic.scout.scout_query_type', 'simple_query_string');
    }

    protected function usesSoftDelete(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * Build document metadata for bulk operations.
     *
     * @return array<string, mixed>
     */
    private function buildDocumentMetadata(Model $model): array
    {
        $metadata = [
            '_index' => $model->searchableAs(),
            '_id' => $model->getScoutKey(),
        ];

        $routing = $model->searchableRouting();
        if ($routing !== null) {
            $metadata['routing'] = $routing;
        }

        return $metadata;
    }
}
