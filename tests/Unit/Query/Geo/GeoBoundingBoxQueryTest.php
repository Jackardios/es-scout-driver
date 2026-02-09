<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Geo;

use Jackardios\EsScoutDriver\Query\Geo\GeoBoundingBoxQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoBoundingBoxQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_geo_bounding_box_query(): void
    {
        $query = new GeoBoundingBoxQuery('location', 40.73, -74.1, 40.01, -71.12);

        $this->assertSame([
            'geo_bounding_box' => [
                'location' => [
                    'top_left' => ['lat' => 40.73, 'lon' => -74.1],
                    'bottom_right' => ['lat' => 40.01, 'lon' => -71.12],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_bounding_box_query_with_all_options(): void
    {
        $query = (new GeoBoundingBoxQuery('location', 40.73, -74.1, 40.01, -71.12))
            ->validationMethod('STRICT')
            ->ignoreUnmapped(true);

        $this->assertSame([
            'geo_bounding_box' => [
                'location' => [
                    'top_left' => ['lat' => 40.73, 'lon' => -74.1],
                    'bottom_right' => ['lat' => 40.01, 'lon' => -71.12],
                ],
                'validation_method' => 'STRICT',
                'ignore_unmapped' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new GeoBoundingBoxQuery('location', 40.73, -74.1, 40.01, -71.12);

        $this->assertSame($query, $query->validationMethod('STRICT'));
        $this->assertSame($query, $query->ignoreUnmapped(true));
    }
}
