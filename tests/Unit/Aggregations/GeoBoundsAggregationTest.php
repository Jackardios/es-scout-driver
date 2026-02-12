<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\GeoBoundsAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoBoundsAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_geo_bounds_aggregation(): void
    {
        $agg = new GeoBoundsAggregation('location');

        $this->assertSame([
            'geo_bounds' => [
                'field' => 'location',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_bounds_aggregation_with_wrap_longitude_true(): void
    {
        $agg = (new GeoBoundsAggregation('location'))
            ->wrapLongitude();

        $this->assertSame([
            'geo_bounds' => [
                'field' => 'location',
                'wrap_longitude' => true,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_geo_bounds_aggregation_with_wrap_longitude_false(): void
    {
        $agg = (new GeoBoundsAggregation('location'))
            ->wrapLongitude(false);

        $this->assertSame([
            'geo_bounds' => [
                'field' => 'location',
                'wrap_longitude' => false,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new GeoBoundsAggregation('location');

        $this->assertSame($agg, $agg->wrapLongitude());
    }
}
