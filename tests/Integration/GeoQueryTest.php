<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

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
}
