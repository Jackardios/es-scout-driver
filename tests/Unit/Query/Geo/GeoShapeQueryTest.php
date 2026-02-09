<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Geo;

use Jackardios\EsScoutDriver\Query\Geo\GeoShapeQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoShapeQueryTest extends TestCase
{
    #[Test]
    public function it_builds_geo_shape_query_with_envelope(): void
    {
        $query = (new GeoShapeQuery('location'))
            ->envelope([[13.0, 53.0], [14.0, 52.0]]);

        $this->assertSame([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'envelope',
                        'coordinates' => [[13.0, 53.0], [14.0, 52.0]],
                    ],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_shape_query_with_polygon(): void
    {
        $query = (new GeoShapeQuery('location'))
            ->polygon([[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]]);

        $this->assertSame([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'polygon',
                        'coordinates' => [[[0.0, 0.0], [10.0, 0.0], [10.0, 10.0], [0.0, 10.0], [0.0, 0.0]]],
                    ],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_shape_query_with_point(): void
    {
        $query = (new GeoShapeQuery('location'))
            ->point([-77.03, 38.89]);

        $this->assertSame([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'point',
                        'coordinates' => [-77.03, 38.89],
                    ],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_shape_query_with_circle(): void
    {
        $query = (new GeoShapeQuery('location'))
            ->circle(-77.03, 38.89, '100m');

        $this->assertSame([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'circle',
                        'coordinates' => [-77.03, 38.89],
                        'radius' => '100m',
                    ],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_shape_query_with_custom_shape(): void
    {
        $query = (new GeoShapeQuery('location'))
            ->shape([
                'type' => 'multipoint',
                'coordinates' => [[102.0, 2.0], [103.0, 2.0]],
            ]);

        $this->assertSame([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'multipoint',
                        'coordinates' => [[102.0, 2.0], [103.0, 2.0]],
                    ],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_shape_query_with_indexed_shape(): void
    {
        $query = (new GeoShapeQuery('location'))
            ->indexedShape('shapes', 'deu', 'location');

        $this->assertSame([
            'geo_shape' => [
                'location' => [
                    'indexed_shape' => [
                        'index' => 'shapes',
                        'id' => 'deu',
                        'path' => 'location',
                    ],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_shape_query_with_relation(): void
    {
        $query = (new GeoShapeQuery('location'))
            ->envelope([[13.0, 53.0], [14.0, 52.0]])
            ->relation('within');

        $this->assertSame([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'envelope',
                        'coordinates' => [[13.0, 53.0], [14.0, 52.0]],
                    ],
                    'relation' => 'within',
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_geo_shape_query_with_ignore_unmapped(): void
    {
        $query = (new GeoShapeQuery('location'))
            ->envelope([[13.0, 53.0], [14.0, 52.0]])
            ->ignoreUnmapped(true);

        $this->assertSame([
            'geo_shape' => [
                'location' => [
                    'shape' => [
                        'type' => 'envelope',
                        'coordinates' => [[13.0, 53.0], [14.0, 52.0]],
                    ],
                ],
                'ignore_unmapped' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new GeoShapeQuery('location');

        $this->assertSame($query, $query->shape(['type' => 'point', 'coordinates' => [0, 0]]));
        $this->assertSame($query, $query->envelope([[0, 0], [1, 1]]));
        $this->assertSame($query, $query->polygon([[0, 0], [1, 0], [1, 1], [0, 0]]));
        $this->assertSame($query, $query->point([0, 0]));
        $this->assertSame($query, $query->circle(0, 0, '10m'));
        $this->assertSame($query, $query->indexedShape('idx', 'id'));
        $this->assertSame($query, $query->relation('within'));
        $this->assertSame($query, $query->ignoreUnmapped(true));
    }
}
