<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Aggregations\Bucket\DateHistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\HistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\RangeAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\CardinalityAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\MaxAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\MinAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\StatsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\SumAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AggFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_terms_aggregation(): void
    {
        $agg = Agg::terms('author');
        $this->assertInstanceOf(TermsAggregation::class, $agg);
        $this->assertSame(['terms' => ['field' => 'author']], $agg->toArray());
    }

    #[Test]
    public function it_creates_avg_aggregation(): void
    {
        $agg = Agg::avg('price');
        $this->assertInstanceOf(AvgAggregation::class, $agg);
        $this->assertSame(['avg' => ['field' => 'price']], $agg->toArray());
    }

    #[Test]
    public function it_creates_sum_aggregation(): void
    {
        $agg = Agg::sum('price');
        $this->assertInstanceOf(SumAggregation::class, $agg);
        $this->assertSame(['sum' => ['field' => 'price']], $agg->toArray());
    }

    #[Test]
    public function it_creates_min_aggregation(): void
    {
        $agg = Agg::min('price');
        $this->assertInstanceOf(MinAggregation::class, $agg);
        $this->assertSame(['min' => ['field' => 'price']], $agg->toArray());
    }

    #[Test]
    public function it_creates_max_aggregation(): void
    {
        $agg = Agg::max('price');
        $this->assertInstanceOf(MaxAggregation::class, $agg);
        $this->assertSame(['max' => ['field' => 'price']], $agg->toArray());
    }

    #[Test]
    public function it_creates_stats_aggregation(): void
    {
        $agg = Agg::stats('price');
        $this->assertInstanceOf(StatsAggregation::class, $agg);
        $this->assertSame(['stats' => ['field' => 'price']], $agg->toArray());
    }

    #[Test]
    public function it_creates_cardinality_aggregation(): void
    {
        $agg = Agg::cardinality('author');
        $this->assertInstanceOf(CardinalityAggregation::class, $agg);
        $this->assertSame(['cardinality' => ['field' => 'author']], $agg->toArray());
    }

    #[Test]
    public function it_creates_histogram_aggregation(): void
    {
        $agg = Agg::histogram('price', 10);
        $this->assertInstanceOf(HistogramAggregation::class, $agg);
        $this->assertSame(['histogram' => ['field' => 'price', 'interval' => 10]], $agg->toArray());
    }

    #[Test]
    public function it_creates_date_histogram_aggregation(): void
    {
        $agg = Agg::dateHistogram('created_at', 'month');
        $this->assertInstanceOf(DateHistogramAggregation::class, $agg);
        $this->assertSame(['date_histogram' => ['field' => 'created_at', 'calendar_interval' => 'month']], $agg->toArray());
    }

    #[Test]
    public function it_creates_range_aggregation(): void
    {
        $agg = Agg::range('price');
        $this->assertInstanceOf(RangeAggregation::class, $agg);
    }

    #[Test]
    public function it_supports_fluent_api(): void
    {
        $agg = Agg::terms('author')
            ->size(10)
            ->orderByCount('desc')
            ->agg('avg_price', Agg::avg('price'));

        $expected = [
            'terms' => [
                'field' => 'author',
                'size' => 10,
                'order' => ['_count' => 'desc'],
            ],
            'aggs' => [
                'avg_price' => ['avg' => ['field' => 'price']],
            ],
        ];

        $this->assertSame($expected, $agg->toArray());
    }
}
