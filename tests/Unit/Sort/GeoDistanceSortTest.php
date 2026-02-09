<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Sort;

use Jackardios\EsScoutDriver\Sort\GeoDistanceSort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoDistanceSortTest extends TestCase
{
    #[Test]
    public function it_builds_basic_geo_distance_sort(): void
    {
        $sort = new GeoDistanceSort('location', 40.7, -74.0);

        $this->assertSame([
            '_geo_distance' => [
                'location' => ['lat' => 40.7, 'lon' => -74.0],
                'order' => 'asc',
                'unit' => 'km',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_sort_with_options(): void
    {
        $sort = (new GeoDistanceSort('location', 40.7, -74.0))
            ->desc()
            ->unit('mi')
            ->mode('min')
            ->distanceType('plane');

        $this->assertSame([
            '_geo_distance' => [
                'location' => ['lat' => 40.7, 'lon' => -74.0],
                'order' => 'desc',
                'unit' => 'mi',
                'mode' => 'min',
                'distance_type' => 'plane',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_sort_with_ignore_unmapped(): void
    {
        $sort = (new GeoDistanceSort('location', 40.7, -74.0))
            ->ignoreUnmapped();

        $this->assertSame([
            '_geo_distance' => [
                'location' => ['lat' => 40.7, 'lon' => -74.0],
                'order' => 'asc',
                'unit' => 'km',
                'ignore_unmapped' => true,
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $sort = new GeoDistanceSort('location', 40.7, -74.0);

        $this->assertSame($sort, $sort->asc());
        $this->assertSame($sort, $sort->desc());
        $this->assertSame($sort, $sort->unit('mi'));
        $this->assertSame($sort, $sort->mode('min'));
        $this->assertSame($sort, $sort->distanceType('plane'));
    }
}
