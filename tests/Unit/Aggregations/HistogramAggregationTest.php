<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\HistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
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

    #[Test]
    public function it_builds_histogram_with_hard_bounds(): void
    {
        $agg = (new HistogramAggregation('price', 10))
            ->hardBounds(0, 1000);

        $this->assertSame([
            'histogram' => [
                'field' => 'price',
                'interval' => 10,
                'hard_bounds' => ['min' => 0, 'max' => 1000],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_histogram_with_order(): void
    {
        $agg = (new HistogramAggregation('price', 10))
            ->order('_count', 'desc');

        $this->assertSame([
            'histogram' => [
                'field' => 'price',
                'interval' => 10,
                'order' => ['_count' => 'desc'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_histogram_with_order_by_key(): void
    {
        $agg = (new HistogramAggregation('price', 10))
            ->order('_key', 'asc');

        $this->assertSame([
            'histogram' => [
                'field' => 'price',
                'interval' => 10,
                'order' => ['_key' => 'asc'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_histogram_with_missing(): void
    {
        $agg = (new HistogramAggregation('price', 10))
            ->missing('0');

        $this->assertSame([
            'histogram' => [
                'field' => 'price',
                'interval' => 10,
                'missing' => '0',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_histogram_with_sub_aggregations(): void
    {
        $agg = (new HistogramAggregation('price', 10))
            ->agg('avg_rating', new AvgAggregation('rating'));

        $result = $agg->toArray();

        $this->assertArrayHasKey('aggs', $result);
        $this->assertSame([
            'avg_rating' => ['avg' => ['field' => 'rating']],
        ], $result['aggs']);
    }

    #[Test]
    public function it_builds_histogram_with_multiple_sub_aggregations(): void
    {
        $agg = (new HistogramAggregation('price', 10))
            ->agg('avg_rating', new AvgAggregation('rating'))
            ->agg('avg_sales', new AvgAggregation('sales'));

        $result = $agg->toArray();

        $this->assertCount(2, $result['aggs']);
        $this->assertArrayHasKey('avg_rating', $result['aggs']);
        $this->assertArrayHasKey('avg_sales', $result['aggs']);
    }

    #[Test]
    public function it_returns_fluent_interface_for_all_methods(): void
    {
        $agg = new HistogramAggregation('price', 10);

        $this->assertSame($agg, $agg->hardBounds(0, 100));
        $this->assertSame($agg, $agg->order('_count', 'desc'));
        $this->assertSame($agg, $agg->missing('0'));
        $this->assertSame($agg, $agg->offset(5));
        $this->assertSame($agg, $agg->agg('avg', new AvgAggregation('field')));
    }

    #[Test]
    public function keyed_can_be_set_to_false(): void
    {
        $agg = (new HistogramAggregation('price', 10))
            ->keyed(false);

        $this->assertSame([
            'histogram' => [
                'field' => 'price',
                'interval' => 10,
                'keyed' => false,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_histogram_with_float_bounds(): void
    {
        $agg = (new HistogramAggregation('rating', 0.5))
            ->extendedBounds(0.0, 5.0)
            ->hardBounds(0.0, 5.0);

        $this->assertSame([
            'histogram' => [
                'field' => 'rating',
                'interval' => 0.5,
                'extended_bounds' => ['min' => 0.0, 'max' => 5.0],
                'hard_bounds' => ['min' => 0.0, 'max' => 5.0],
            ],
        ], $agg->toArray());
    }
}
