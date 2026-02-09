<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Jackardios\EsScoutDriver\Engine\NullEngine;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullEngineTest extends TestCase
{
    private NullEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new NullEngine();
    }

    #[Test]
    public function search_returns_empty_result(): void
    {
        $builder = $this->createMock(Builder::class);
        $result = $this->engine->search($builder);

        $this->assertSame(0, $result['hits']['total']['value']);
        $this->assertSame([], $result['hits']['hits']);
    }

    #[Test]
    public function paginate_returns_empty_result(): void
    {
        $builder = $this->createMock(Builder::class);
        $result = $this->engine->paginate($builder, 10, 1);

        $this->assertSame(0, $result['hits']['total']['value']);
    }

    #[Test]
    public function map_ids_returns_empty_collection(): void
    {
        $result = $this->engine->mapIds([]);
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function map_returns_empty_collection(): void
    {
        $builder = $this->createMock(Builder::class);
        $result = $this->engine->map($builder, [], $this->createMock(\Illuminate\Database\Eloquent\Model::class));

        $this->assertInstanceOf(EloquentCollection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function lazy_map_returns_empty_lazy_collection(): void
    {
        $builder = $this->createMock(Builder::class);
        $result = $this->engine->lazyMap($builder, [], $this->createMock(\Illuminate\Database\Eloquent\Model::class));

        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    #[Test]
    public function get_total_count_returns_zero(): void
    {
        $this->assertSame(0, $this->engine->getTotalCount([]));
    }

    #[Test]
    public function search_raw_returns_empty_result(): void
    {
        $result = $this->engine->searchRaw([]);

        $this->assertSame(0, $result['hits']['total']['value']);
    }

    #[Test]
    public function connection_returns_same_instance(): void
    {
        $this->assertSame($this->engine, $this->engine->connection('any'));
    }

    #[Test]
    public function open_point_in_time_returns_meaningful_id(): void
    {
        $pitId = $this->engine->openPointInTime('index');

        $this->assertNotEmpty($pitId);
        $this->assertStringStartsWith('null-pit-', $pitId);
    }

    #[Test]
    public function operations_do_not_throw(): void
    {
        $this->engine->update(new EloquentCollection());
        $this->engine->delete(new EloquentCollection());
        $this->engine->flush($this->createMock(\Illuminate\Database\Eloquent\Model::class));
        $this->engine->createIndex('test');
        $this->engine->deleteIndex('test');
        $this->engine->closePointInTime('pit-id');

        $this->assertTrue(true); // just verifying no exceptions
    }

    #[Test]
    public function count_raw_returns_zero(): void
    {
        $this->assertSame(0, $this->engine->countRaw(['index' => 'test']));
    }

    #[Test]
    public function delete_by_query_raw_returns_empty_result(): void
    {
        $result = $this->engine->deleteByQueryRaw(['index' => 'test']);

        $this->assertSame(0, $result['deleted']);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['failures']);
    }

    #[Test]
    public function update_by_query_raw_returns_empty_result(): void
    {
        $result = $this->engine->updateByQueryRaw(['index' => 'test']);

        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['failures']);
    }
}
