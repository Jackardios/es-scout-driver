<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\HistogramAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HistogramAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_histogram_aggregation(): void
    {
        $agg = new HistogramAggregation('price', 10);

        $this->assertSame([
            'histogram' => ['field' => 'price', 'interval' => 10],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_histogram_with_float_interval(): void
    {
        $agg = new HistogramAggregation('rating', 0.5);

        $this->assertSame([
            'histogram' => ['field' => 'rating', 'interval' => 0.5],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_histogram_with_all_options(): void
    {
        $agg = (new HistogramAggregation('price', 10))
            ->minDocCount(0)
            ->extendedBounds(0, 100)
            ->offset(5)
            ->keyed();

        $this->assertSame([
            'histogram' => [
                'field' => 'price',
                'interval' => 10,
                'min_doc_count' => 0,
                'extended_bounds' => ['min' => 0, 'max' => 100],
                'offset' => 5,
                'keyed' => true,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new HistogramAggregation('price', 10);

        $this->assertSame($agg, $agg->minDocCount(0));
        $this->assertSame($agg, $agg->extendedBounds(0, 100));
        $this->assertSame($agg, $agg->keyed());
    }
}
