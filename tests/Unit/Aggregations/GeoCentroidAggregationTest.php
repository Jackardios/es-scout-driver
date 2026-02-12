<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\GeoCentroidAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GeoCentroidAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_geo_centroid_aggregation(): void
    {
        $agg = new GeoCentroidAggregation('location');

        $this->assertSame([
            'geo_centroid' => [
                'field' => 'location',
            ],
        ], $agg->toArray());
    }
}
