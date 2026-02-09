<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\DateHistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DateHistogramAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_date_histogram_aggregation(): void
    {
        $agg = new DateHistogramAggregation('created_at', 'month');

        $this->assertSame([
            'date_histogram' => ['field' => 'created_at', 'calendar_interval' => 'month'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_format(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'day'))
            ->format('yyyy-MM-dd');

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'day',
                'format' => 'yyyy-MM-dd',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_all_options(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->format('yyyy-MM')
            ->timeZone('America/New_York')
            ->minDocCount(0)
            ->offset('+6h');

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'month',
                'format' => 'yyyy-MM',
                'time_zone' => 'America/New_York',
                'min_doc_count' => 0,
                'offset' => '+6h',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new DateHistogramAggregation('created_at', 'month');

        $this->assertSame($agg, $agg->format('yyyy-MM'));
        $this->assertSame($agg, $agg->timeZone('UTC'));
        $this->assertSame($agg, $agg->minDocCount(0));
    }

    #[Test]
    public function it_builds_date_histogram_with_fixed_interval(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'day'))
            ->fixedInterval('1h');

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'day',
                'fixed_interval' => '1h',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_extended_bounds(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->extendedBounds('2024-01-01', '2024-12-31');

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'month',
                'extended_bounds' => ['min' => '2024-01-01', 'max' => '2024-12-31'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_hard_bounds(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->hardBounds('2024-01-01', '2024-12-31');

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'month',
                'hard_bounds' => ['min' => '2024-01-01', 'max' => '2024-12-31'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_keyed(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->keyed();

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'month',
                'keyed' => true,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function keyed_can_be_set_to_false(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->keyed(false);

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'month',
                'keyed' => false,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_order(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->order('_count', 'desc');

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'month',
                'order' => ['_count' => 'desc'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_order_by_key(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->order('_key', 'asc');

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'month',
                'order' => ['_key' => 'asc'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_missing(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->missing('1970-01-01');

        $this->assertSame([
            'date_histogram' => [
                'field' => 'created_at',
                'calendar_interval' => 'month',
                'missing' => '1970-01-01',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_date_histogram_with_sub_aggregations(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->agg('avg_sales', new AvgAggregation('sales'));

        $result = $agg->toArray();

        $this->assertArrayHasKey('aggs', $result);
        $this->assertSame([
            'avg_sales' => ['avg' => ['field' => 'sales']],
        ], $result['aggs']);
    }

    #[Test]
    public function it_builds_date_histogram_with_multiple_sub_aggregations(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->agg('avg_sales', new AvgAggregation('sales'))
            ->agg('avg_views', new AvgAggregation('views'));

        $result = $agg->toArray();

        $this->assertCount(2, $result['aggs']);
        $this->assertArrayHasKey('avg_sales', $result['aggs']);
        $this->assertArrayHasKey('avg_views', $result['aggs']);
    }

    #[Test]
    public function it_returns_fluent_interface_for_all_methods(): void
    {
        $agg = new DateHistogramAggregation('created_at', 'month');

        $this->assertSame($agg, $agg->fixedInterval('1h'));
        $this->assertSame($agg, $agg->extendedBounds('2024-01-01', '2024-12-31'));
        $this->assertSame($agg, $agg->hardBounds('2024-01-01', '2024-12-31'));
        $this->assertSame($agg, $agg->keyed());
        $this->assertSame($agg, $agg->order('_count', 'desc'));
        $this->assertSame($agg, $agg->missing('1970-01-01'));
        $this->assertSame($agg, $agg->offset('+1h'));
        $this->assertSame($agg, $agg->agg('avg', new AvgAggregation('field')));
    }

    #[Test]
    public function it_builds_date_histogram_with_comprehensive_options(): void
    {
        $agg = (new DateHistogramAggregation('created_at', 'month'))
            ->fixedInterval('30d')
            ->format('yyyy-MM-dd')
            ->timeZone('Europe/London')
            ->minDocCount(1)
            ->extendedBounds('2024-01-01', '2024-12-31')
            ->hardBounds('2023-01-01', '2025-12-31')
            ->offset('+1d')
            ->order('_key', 'desc')
            ->missing('2020-01-01')
            ->keyed();

        $result = $agg->toArray();

        $this->assertSame('created_at', $result['date_histogram']['field']);
        $this->assertSame('month', $result['date_histogram']['calendar_interval']);
        $this->assertSame('30d', $result['date_histogram']['fixed_interval']);
        $this->assertSame('yyyy-MM-dd', $result['date_histogram']['format']);
        $this->assertSame('Europe/London', $result['date_histogram']['time_zone']);
        $this->assertSame(1, $result['date_histogram']['min_doc_count']);
        $this->assertSame(['min' => '2024-01-01', 'max' => '2024-12-31'], $result['date_histogram']['extended_bounds']);
        $this->assertSame(['min' => '2023-01-01', 'max' => '2025-12-31'], $result['date_histogram']['hard_bounds']);
        $this->assertSame('+1d', $result['date_histogram']['offset']);
        $this->assertSame(['_key' => 'desc'], $result['date_histogram']['order']);
        $this->assertSame('2020-01-01', $result['date_histogram']['missing']);
        $this->assertTrue($result['date_histogram']['keyed']);
    }
}
