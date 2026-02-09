<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Elastic\Elasticsearch\Client;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as ScoutEngine;

final class NullEngine extends ScoutEngine implements EngineInterface
{
    private array $emptyResult;

    public function __construct()
    {
        $this->emptyResult = [
            'hits' => [
                'total' => ['value' => 0],
                'max_score' => null,
                'hits' => [],
            ],
        ];
    }

    public function update($models): void {}

    public function delete($models): void {}

    public function search(Builder $builder): array
    {
        return $this->emptyResult;
    }

    public function paginate(Builder $builder, $perPage, $page): array
    {
        return $this->emptyResult;
    }

    public function mapIds($results): Collection
    {
        return Collection::make();
    }

    public function map(Builder $builder, $results, $model): EloquentCollection
    {
        return EloquentCollection::make();
    }

    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        return LazyCollection::make();
    }

    public function getTotalCount($results): int
    {
        return 0;
    }

    public function flush($model): void {}

    public function createIndex($name, array $options = []): void {}

    public function deleteIndex($name): void {}

    public function searchRaw(array $params): array
    {
        return $this->emptyResult;
    }

    public function connection(string $connection): static
    {
        return $this;
    }

    public function openPointInTime(string $indexName, ?string $keepAlive = null): string
    {
        return 'null-pit-' . uniqid('', true);
    }

    public function closePointInTime(string $pointInTimeId): void {}

    public function countRaw(array $params): int
    {
        return 0;
    }

    public function deleteByQueryRaw(array $params): array
    {
        return [
            'took' => 0,
            'timed_out' => false,
            'total' => 0,
            'deleted' => 0,
            'batches' => 0,
            'version_conflicts' => 0,
            'noops' => 0,
            'failures' => [],
        ];
    }

    public function updateByQueryRaw(array $params): array
    {
        return [
            'took' => 0,
            'timed_out' => false,
            'total' => 0,
            'updated' => 0,
            'batches' => 0,
            'version_conflicts' => 0,
            'noops' => 0,
            'failures' => [],
        ];
    }

    public function getClient(): ?Client
    {
        return null;
    }
}
