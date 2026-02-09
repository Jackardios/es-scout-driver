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
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as ScoutEngine;

class Engine extends ScoutEngine implements EngineInterface
{
    use HandlesBulkResponse;

    public function __construct(
        protected Client $client,
        protected bool $refreshDocuments = false,
    ) {}

    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $models->first()->searchableWith()
            ? $models->loadMissing($models->first()->searchableWith())
            : null;

        $body = [];

        foreach ($models as $model) {
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
            return;
        }

        $params = ['body' => $body];

        if ($this->refreshDocuments) {
            $params['refresh'] = 'true';
        }

        /** @var ElasticsearchResponse $response */
        $response = $this->client->bulk($params);
        $this->handleBulkResponse($response->asArray());
    }

    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $body = [];

        foreach ($models as $model) {
            $metadata = $this->buildDocumentMetadata($model);
            $body[] = ['delete' => $metadata];
        }

        $params = ['body' => $body];

        if ($this->refreshDocuments) {
            $params['refresh'] = 'true';
        }

        /** @var ElasticsearchResponse $response */
        $response = $this->client->bulk($params);
        $this->handleBulkResponse($response->asArray());
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
        if (!isset($results['hits']['hits']) || count($results['hits']['hits']) === 0) {
            return EloquentCollection::make();
        }

        $ids = Collection::make($results['hits']['hits'])->pluck('_id')->values()->all();
        $objectIdPositions = array_flip($ids);

        $models = $model->getScoutModelsByIds($builder, $ids)
            ->filter(fn($model) => isset($objectIdPositions[$model->getScoutKey()]))
            ->sortBy(fn($model) => $objectIdPositions[$model->getScoutKey()])
            ->values();

        return new EloquentCollection($models);
    }

    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if (!isset($results['hits']['hits']) || count($results['hits']['hits']) === 0) {
            return LazyCollection::make();
        }

        $ids = Collection::make($results['hits']['hits'])->pluck('_id')->values()->all();
        $objectIdPositions = array_flip($ids);

        return $model->queryScoutModelsByIds($builder, $ids)
            ->cursor()
            ->filter(fn($model) => isset($objectIdPositions[$model->getScoutKey()]))
            ->sortBy(fn($model) => $objectIdPositions[$model->getScoutKey()])
            ->values();
    }

    public function getTotalCount($results): int
    {
        return $results['hits']['total']['value'] ?? 0;
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
        return $clone;
    }

    public function openPointInTime(string $indexName, ?string $keepAlive = null): string
    {
        $params = ['index' => $indexName];

        if ($keepAlive !== null) {
            $params['keep_alive'] = $keepAlive;
        } else {
            $params['keep_alive'] = '5m';
        }

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
            'index' => $builder->model->searchableAs(),
            'body' => [],
        ];

        if ($builder->query !== null && $builder->query !== '') {
            $params['body']['query'] = [
                'query_string' => ['query' => $builder->query],
            ];
        }

        if (!empty($builder->wheres)) {
            $filters = [];

            foreach ($builder->wheres as $field => $value) {
                $filters[] = ['term' => [$field => ['value' => $value]]];
            }

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

        if (method_exists($model, 'searchableRouting') && $model->searchableRouting() !== null) {
            $metadata['routing'] = $model->searchableRouting();
        }

        return $metadata;
    }
}
