<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\FilterAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilterAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_filter_aggregation_with_query_interface(): void
    {
        $agg = new FilterAggregation(new TermQuery('status', 'published'));

        $this->assertSame([
            'filter' => [
                'term' => [
                    'status' => [
                        'value' => 'published',
                    ],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_filter_aggregation_with_array(): void
    {
        $agg = new FilterAggregation(['term' => ['status' => 'published']]);

        $this->assertSame([
            'filter' => [
                'term' => ['status' => 'published'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_filter_aggregation_with_sub_aggregations(): void
    {
        $agg = (new FilterAggregation(new TermQuery('status', 'published')))
            ->agg('avg_price', new AvgAggregation('price'));

        $this->assertSame([
            'filter' => [
                'term' => [
                    'status' => [
                        'value' => 'published',
                    ],
                ],
            ],
            'aggs' => [
                'avg_price' => [
                    'avg' => ['field' => 'price'],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new FilterAggregation(['term' => ['status' => 'published']]);

        $this->assertSame($agg, $agg->agg('test', new AvgAggregation('price')));
    }
}
