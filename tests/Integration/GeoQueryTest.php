<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Store;
use PHPUnit\Framework\Attributes\Test;

final class GeoQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createIndex('stores', [
            'mappings' => [
                'properties' => [
                    'name' => ['type' => 'text'],
                    'location' => ['type' => 'geo_point'],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteIndex('stores');
        parent::tearDown();
    }

    #[Test]
    public function test_geo_distance_query(): void
    {
        // Create stores at known coordinates
        $moscow = Store::factory()->create([
            'name' => 'Moscow Store',
            'lat' => 55.75,
            'lon' => 37.61,
        ]);

        $london = Store::factory()->create([
            'name' => 'London Store',
            'lat' => 51.50,
            'lon' => -0.12,
        ]);

        $newYork = Store::factory()->create([
            'name' => 'New York Store',
            'lat' => 40.71,
            'lon' => -74.00,
        ]);

        $moscow->searchable();
        $london->searchable();
        $newYork->searchable();

        $this->refreshIndex('stores');

        // Search within 1000km of Moscow
        $result = Store::searchQuery(Query::geoDistance(
            field: 'location',
            lat: 55.75,
            lon: 37.61,
            distance: '1000km'
        ))->execute();

        $this->assertSame(1, $result->total);

        $models = $result->models();
        $this->assertCount(1, $models);
        $this->assertSame($moscow->id, $models->first()->id);
        $this->assertSame('Moscow Store', $models->first()->name);
    }

    #[Test]
    public function test_geo_bounding_box_query(): void
    {
        // Create stores at known coordinates
        $moscow = Store::factory()->create([
            'name' => 'Moscow Store',
            'lat' => 55.75,
            'lon' => 37.61,
        ]);

        $london = Store::factory()->create([
            'name' => 'London Store',
            'lat' => 51.50,
            'lon' => -0.12,
        ]);

        $newYork = Store::factory()->create([
            'name' => 'New York Store',
            'lat' => 40.71,
            'lon' => -74.00,
        ]);

        $moscow->searchable();
        $london->searchable();
        $newYork->searchable();

        $this->refreshIndex('stores');

        // Define a bounding box that includes Moscow and London, but not New York
        // Top-left: 60°N, 10°W
        // Bottom-right: 40°N, 50°E
        $result = Store::searchQuery(Query::geoBoundingBox(
            field: 'location',
            topLeftLat: 60.0,
            topLeftLon: -10.0,
            bottomRightLat: 40.0,
            bottomRightLon: 50.0
        ))->execute();

        $this->assertSame(2, $result->total);

        $models = $result->models();
        $this->assertCount(2, $models);

        $ids = $models->pluck('id')->toArray();
        $this->assertContains($moscow->id, $ids);
        $this->assertContains($london->id, $ids);
        $this->assertNotContains($newYork->id, $ids);
    }

    #[Test]
    public function test_geo_distance_aggregation(): void
    {
        $nearby = Store::factory()->create([
            'name' => 'Nearby Store',
            'lat' => 55.76,
            'lon' => 37.62,
        ]);

        $medium = Store::factory()->create([
            'name' => 'Medium Store',
            'lat' => 55.90,
            'lon' => 37.80,
        ]);

        $far = Store::factory()->create([
            'name' => 'Far Store',
            'lat' => 56.50,
            'lon' => 38.50,
        ]);

        $nearby->searchable();
        $medium->searchable();
        $far->searchable();

        $this->refreshIndex('stores');

        $result = Store::searchQuery(Query::matchAll())
            ->aggregate('distance_ranges', Agg::geoDistance('location', 55.75, 37.61)
                ->range(to: 5, key: 'nearby')
                ->range(from: 5, to: 50, key: 'medium')
                ->range(from: 50, key: 'far')
                ->unit('km'))
            ->execute();

        $buckets = $result->buckets('distance_ranges');
        $this->assertCount(3, $buckets);

        $bucketsByKey = $buckets->keyBy('key');
        $this->assertSame(1, $bucketsByKey['nearby']['doc_count']);
        $this->assertSame(1, $bucketsByKey['medium']['doc_count']);
        $this->assertSame(1, $bucketsByKey['far']['doc_count']);
    }

    #[Test]
    public function test_geo_bounds_aggregation(): void
    {
        $moscow = Store::factory()->create([
            'name' => 'Moscow Store',
            'lat' => 55.75,
            'lon' => 37.61,
        ]);

        $london = Store::factory()->create([
            'name' => 'London Store',
            'lat' => 51.50,
            'lon' => -0.12,
        ]);

        $moscow->searchable();
        $london->searchable();

        $this->refreshIndex('stores');

        $result = Store::searchQuery(Query::matchAll())
            ->aggregate('bounds', Agg::geoBounds('location'))
            ->execute();

        $bounds = $result->aggregation('bounds')['bounds'];

        $this->assertArrayHasKey('top_left', $bounds);
        $this->assertArrayHasKey('bottom_right', $bounds);
        $this->assertGreaterThan(51.0, $bounds['top_left']['lat']);
        $this->assertLessThan(56.0, $bounds['top_left']['lat']);
    }

    #[Test]
    public function test_geo_centroid_aggregation(): void
    {
        $store1 = Store::factory()->create([
            'name' => 'Store 1',
            'lat' => 55.75,
            'lon' => 37.61,
        ]);

        $store2 = Store::factory()->create([
            'name' => 'Store 2',
            'lat' => 55.85,
            'lon' => 37.71,
        ]);

        $store1->searchable();
        $store2->searchable();

        $this->refreshIndex('stores');

        $result = Store::searchQuery(Query::matchAll())
            ->aggregate('center', Agg::geoCentroid('location'))
            ->execute();

        $centroid = $result->aggregation('center')['location'];

        $this->assertArrayHasKey('lat', $centroid);
        $this->assertArrayHasKey('lon', $centroid);
        $this->assertEqualsWithDelta(55.80, $centroid['lat'], 0.01);
        $this->assertEqualsWithDelta(37.66, $centroid['lon'], 0.01);
    }
}
