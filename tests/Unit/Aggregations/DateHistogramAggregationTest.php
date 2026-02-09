<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\DateHistogramAggregation;
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
}
