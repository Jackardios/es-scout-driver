<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Aggregations\Bucket\GeoDistanceAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use Jackardios\EsScoutDriver\Enums\DistanceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoDistanceAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_geo_distance_aggregation_with_fluent_ranges(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->range(to: 100)
            ->range(from: 100, to: 300)
            ->range(from: 300);

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [
                    ['to' => 100],
                    ['from' => 100, 'to' => 300],
                    ['from' => 300],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_aggregation_with_ranges_array(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->ranges([
                ['to' => 100],
                ['from' => 100, 'to' => 300],
                ['from' => 300],
            ]);

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [
                    ['to' => 100],
                    ['from' => 100, 'to' => 300],
                    ['from' => 300],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_aggregation_with_keyed_ranges(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->range(to: 5, key: 'nearby')
            ->range(from: 5, to: 20, key: 'medium')
            ->range(from: 20, key: 'far');

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [
                    ['to' => 5, 'key' => 'nearby'],
                    ['from' => 5, 'to' => 20, 'key' => 'medium'],
                    ['from' => 20, 'key' => 'far'],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_aggregation_with_unit(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->range(to: 100)
            ->unit('km');

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [['to' => 100]],
                'unit' => 'km',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_aggregation_with_distance_type_enum(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->range(to: 100)
            ->distanceType(DistanceType::Arc);

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [['to' => 100]],
                'distance_type' => 'arc',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_aggregation_with_distance_type_string(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->range(to: 100)
            ->distanceType('plane');

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [['to' => 100]],
                'distance_type' => 'plane',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_aggregation_with_keyed(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->range(to: 100)
            ->keyed();

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [['to' => 100]],
                'keyed' => true,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_aggregation_with_all_options(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->range(to: 5, key: 'nearby')
            ->range(from: 5, key: 'far')
            ->unit('km')
            ->distanceType(DistanceType::Plane)
            ->keyed();

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [
                    ['to' => 5, 'key' => 'nearby'],
                    ['from' => 5, 'key' => 'far'],
                ],
                'unit' => 'km',
                'distance_type' => 'plane',
                'keyed' => true,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_distance_aggregation_with_sub_aggregations(): void
    {
        $agg = (new GeoDistanceAggregation('location', 52.3760, 4.894))
            ->range(to: 100)
            ->agg('avg_price', new AvgAggregation('price'));

        $this->assertSame([
            'geo_distance' => [
                'field' => 'location',
                'origin' => ['lat' => 52.3760, 'lon' => 4.894],
                'ranges' => [['to' => 100]],
            ],
            'aggs' => [
                'avg_price' => ['avg' => ['field' => 'price']],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_throws_exception_for_empty_ranges(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('GeoDistanceAggregation requires at least one range.');

        (new GeoDistanceAggregation('location', 52.3760, 4.894))->toArray();
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new GeoDistanceAggregation('location', 52.3760, 4.894);

        $this->assertSame($agg, $agg->range(to: 100));
        $this->assertSame($agg, $agg->ranges([['to' => 100]]));
        $this->assertSame($agg, $agg->unit('km'));
        $this->assertSame($agg, $agg->distanceType('arc'));
        $this->assertSame($agg, $agg->keyed());
        $this->assertSame($agg, $agg->agg('test', new AvgAggregation('price')));
    }
}
