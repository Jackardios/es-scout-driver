<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\CompositeAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\SumAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompositeAggregationTest extends TestCase
{
    #[Test]
    public function it_throws_exception_when_no_sources(): void
    {
        $agg = new CompositeAggregation();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CompositeAggregation requires at least one source.');

        $agg->toArray();
    }

    #[Test]
    public function it_builds_composite_aggregation_with_terms_source(): void
    {
        $agg = (new CompositeAggregation())
            ->termsSource('product', 'product_id');

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['product' => ['terms' => ['field' => 'product_id']]],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_terms_source_with_order(): void
    {
        $agg = (new CompositeAggregation())
            ->termsSource('product', 'product_id', 'asc');

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['product' => ['terms' => ['field' => 'product_id', 'order' => 'asc']]],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_date_histogram_source(): void
    {
        $agg = (new CompositeAggregation())
            ->dateHistogramSource('date', 'created_at', 'day');

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['date' => ['date_histogram' => [
                        'field' => 'created_at',
                        'calendar_interval' => 'day',
                    ]]],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_date_histogram_source_with_format(): void
    {
        $agg = (new CompositeAggregation())
            ->dateHistogramSource('date', 'created_at', 'month', 'yyyy-MM');

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['date' => ['date_histogram' => [
                        'field' => 'created_at',
                        'calendar_interval' => 'month',
                        'format' => 'yyyy-MM',
                    ]]],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_histogram_source(): void
    {
        $agg = (new CompositeAggregation())
            ->histogramSource('price_range', 'price', 100);

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['price_range' => ['histogram' => [
                        'field' => 'price',
                        'interval' => 100,
                    ]]],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_custom_source(): void
    {
        $agg = (new CompositeAggregation())
            ->addSource('geo', ['geotile_grid' => ['field' => 'location', 'precision' => 8]]);

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['geo' => ['geotile_grid' => ['field' => 'location', 'precision' => 8]]],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_multiple_sources(): void
    {
        $agg = (new CompositeAggregation())
            ->termsSource('shop', 'shop_id')
            ->dateHistogramSource('date', 'created_at', 'day');

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['shop' => ['terms' => ['field' => 'shop_id']]],
                    ['date' => ['date_histogram' => [
                        'field' => 'created_at',
                        'calendar_interval' => 'day',
                    ]]],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_size(): void
    {
        $agg = (new CompositeAggregation())
            ->termsSource('product', 'product_id')
            ->size(100);

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['product' => ['terms' => ['field' => 'product_id']]],
                ],
                'size' => 100,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_after(): void
    {
        $agg = (new CompositeAggregation())
            ->termsSource('product', 'product_id')
            ->after(['product' => 'abc123']);

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['product' => ['terms' => ['field' => 'product_id']]],
                ],
                'after' => ['product' => 'abc123'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_composite_aggregation_with_sub_aggregation(): void
    {
        $agg = (new CompositeAggregation())
            ->termsSource('product', 'product_id')
            ->agg('total_amount', new SumAggregation('amount'));

        $this->assertSame([
            'composite' => [
                'sources' => [
                    ['product' => ['terms' => ['field' => 'product_id']]],
                ],
            ],
            'aggs' => [
                'total_amount' => ['sum' => ['field' => 'amount']],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new CompositeAggregation();

        $this->assertSame($agg, $agg->termsSource('product', 'product_id'));
        $this->assertSame($agg, $agg->dateHistogramSource('date', 'created_at', 'day'));
        $this->assertSame($agg, $agg->histogramSource('price', 'price', 100));
        $this->assertSame($agg, $agg->addSource('custom', ['terms' => ['field' => 'x']]));
        $this->assertSame($agg, $agg->size(100));
        $this->assertSame($agg, $agg->after(['product' => 'abc']));
        $this->assertSame($agg, $agg->agg('sum', new SumAggregation('amount')));
    }
}
