<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Geo;

use Jackardios\EsScoutDriver\Query\Geo\GeoDistanceQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoDistanceQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_geo_distance_query(): void
    {
        $query = new GeoDistanceQuery('location', 40.73, -73.99, '10km');

        $this->assertSame([
            'geo_distance' => [
                'location' => ['lat' => 40.73, 'lon' => -73.99],
                'distance' => '10km',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_query_with_all_options(): void
    {
        $query = (new GeoDistanceQuery('location', 40.73, -73.99, '10km'))
            ->distanceType('arc')
            ->validationMethod('STRICT')
            ->ignoreUnmapped(true);

        $this->assertSame([
            'geo_distance' => [
                'location' => ['lat' => 40.73, 'lon' => -73.99],
                'distance' => '10km',
                'distance_type' => 'arc',
                'validation_method' => 'STRICT',
                'ignore_unmapped' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new GeoDistanceQuery('location', 40.73, -73.99, '10km');

        $this->assertSame($query, $query->distanceType('arc'));
        $this->assertSame($query, $query->validationMethod('STRICT'));
        $this->assertSame($query, $query->ignoreUnmapped(true));
    }
}
