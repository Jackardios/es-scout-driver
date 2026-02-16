<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Jackardios\EsScoutDriver\Engine\Engine;
use Laravel\Scout\Builder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EngineMapTest extends TestCase
{
    protected function setUp(): void
    {
        FakeMapModel::resetFakeState();
    }

    #[Test]
    public function map_returns_empty_collection_when_results_is_empty_array(): void
    {
        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $result = $engine->map($builder, [], $model);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function map_returns_empty_collection_when_hits_array_is_empty(): void
    {
        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $result = $engine->map($builder, ['hits' => ['hits' => []]], $model);

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function map_hydrates_models_with_hit_metadata(): void
    {
        FakeMapModel::seedRecords(['1', '2']);

        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_index' => 'books', '_score' => 1.5, '_routing' => 'tenant-1', '_source' => ['title' => 'Test']],
                    ['_id' => '2', '_index' => 'books', '_score' => 0.8, '_source' => ['title' => 'Test 2']],
                ],
            ],
        ];

        $result = $engine->map($builder, $results, $model);

        $this->assertCount(2, $result);

        $model1 = $result->first(fn($m) => $m->getScoutKey() === '1');
        $model2 = $result->first(fn($m) => $m->getScoutKey() === '2');

        $this->assertSame(['_id' => '1', '_index' => 'books', '_score' => 1.5, '_routing' => 'tenant-1'], $model1->scoutMetadata);
        $this->assertSame(['_id' => '2', '_index' => 'books', '_score' => 0.8], $model2->scoutMetadata);
    }

    #[Test]
    public function map_excludes_source_from_metadata(): void
    {
        FakeMapModel::seedRecords(['1']);

        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_index' => 'books', '_score' => 1.0, '_source' => ['title' => 'Test', 'content' => 'Body']],
                ],
            ],
        ];

        $result = $engine->map($builder, $results, $model);

        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('_source', $result->first()->scoutMetadata);
        $this->assertSame(['_id' => '1', '_index' => 'books', '_score' => 1.0], $result->first()->scoutMetadata);
    }

    #[Test]
    public function map_filters_models_not_in_results(): void
    {
        FakeMapModel::seedRecords(['1', '2', '3']);

        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_index' => 'books'],
                    ['_id' => '3', '_index' => 'books'],
                ],
            ],
        ];

        $result = $engine->map($builder, $results, $model);

        $this->assertCount(2, $result);
        $ids = $result->map(fn($m) => $m->getScoutKey())->all();
        $this->assertContains('1', $ids);
        $this->assertContains('3', $ids);
        $this->assertNotContains('2', $ids);
    }

    #[Test]
    public function map_preserves_search_result_order(): void
    {
        FakeMapModel::seedRecords(['3', '1', '2']);

        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_index' => 'books'],
                    ['_id' => '2', '_index' => 'books'],
                    ['_id' => '3', '_index' => 'books'],
                ],
            ],
        ];

        $result = $engine->map($builder, $results, $model);

        $this->assertCount(3, $result);
        $this->assertSame(['1', '2', '3'], $result->map(fn($m) => $m->getScoutKey())->all());
    }

    #[Test]
    public function lazy_map_returns_empty_when_results_is_empty(): void
    {
        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $result = $engine->lazyMap($builder, [], $model);

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function lazy_map_returns_empty_when_hits_array_is_empty(): void
    {
        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $result = $engine->lazyMap($builder, ['hits' => ['hits' => []]], $model);

        $this->assertInstanceOf(LazyCollection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function lazy_map_returns_lazy_collection(): void
    {
        FakeMapModel::seedRecords(['1']);

        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_index' => 'books'],
                ],
            ],
        ];

        $result = $engine->lazyMap($builder, $results, $model);

        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    #[Test]
    public function lazy_map_hydrates_and_sorts_correctly(): void
    {
        FakeMapModel::seedRecords(['3', '1', '2']);

        $engine = $this->createEngine();
        $builder = $this->createScoutBuilder();
        $model = new FakeMapModel();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '2', '_index' => 'books', '_score' => 2.0],
                    ['_id' => '1', '_index' => 'books', '_score' => 1.5],
                    ['_id' => '3', '_index' => 'books', '_score' => 1.0],
                ],
            ],
        ];

        $result = $engine->lazyMap($builder, $results, $model);

        $this->assertInstanceOf(LazyCollection::class, $result);

        $models = $result->all();
        $this->assertCount(3, $models);
        $this->assertSame(['2', '1', '3'], array_map(fn($m) => $m->getScoutKey(), $models));

        $this->assertSame(['_id' => '2', '_index' => 'books', '_score' => 2.0], $models[0]->scoutMetadata);
        $this->assertSame(['_id' => '1', '_index' => 'books', '_score' => 1.5], $models[1]->scoutMetadata);
        $this->assertSame(['_id' => '3', '_index' => 'books', '_score' => 1.0], $models[2]->scoutMetadata);
    }

    private function createScoutBuilder(): Builder
    {
        return new Builder(new FakeMapModel(), 'test');
    }

    private function createEngine(): Engine
    {
        $client = \Elastic\Elasticsearch\ClientBuilder::create()
            ->setHosts(['http://localhost:9200'])
            ->build();

        return new Engine($client);
    }
}

final class FakeMapModel extends Model
{
    /** @var array<int, self> */
    public static array $records = [];

    /** @var array<string, mixed> */
    public array $scoutMetadata = [];

    protected $guarded = [];
    public $timestamps = false;

    public static function resetFakeState(): void
    {
        static::$records = [];
    }

    /**
     * @param array<int, string> $ids
     */
    public static function seedRecords(array $ids): void
    {
        static::$records = array_map(static function (string $id) {
            $model = new static();
            $model->id = $id;

            return $model;
        }, $ids);
    }

    public function searchableAs(): string
    {
        return 'books';
    }

    public function getScoutKeyName(): string
    {
        return 'id';
    }

    public function getScoutKey(): string
    {
        return (string) $this->id;
    }

    public function withScoutMetadata(string $key, mixed $value): self
    {
        $this->scoutMetadata[$key] = $value;

        return $this;
    }

    /**
     * @param array<int, string> $ids
     */
    public function getScoutModelsByIds(Builder $builder, array $ids): EloquentCollection
    {
        $idsLookup = array_flip($ids);

        return new EloquentCollection(array_values(array_filter(
            static::$records,
            static fn(self $model): bool => isset($idsLookup[$model->getScoutKey()]),
        )));
    }

    /**
     * @param array<int, string> $ids
     */
    public function queryScoutModelsByIds(Builder $builder, array $ids): FakeMapModelQuery
    {
        return new FakeMapModelQuery($ids, static::$records);
    }
}

final class FakeMapModelQuery
{
    /**
     * @param array<int, string> $ids
     * @param array<int, FakeMapModel> $records
     */
    public function __construct(
        private readonly array $ids,
        private readonly array $records,
    ) {}

    public function cursor(): LazyCollection
    {
        $idsLookup = array_flip($this->ids);

        $filtered = array_values(array_filter(
            $this->records,
            static fn(FakeMapModel $model): bool => isset($idsLookup[$model->getScoutKey()]),
        ));

        return new LazyCollection(function () use ($filtered) {
            foreach ($filtered as $model) {
                yield $model;
            }
        });
    }
}
