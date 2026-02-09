<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\CardinalityAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CardinalityAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_cardinality_aggregation(): void
    {
        $agg = new CardinalityAggregation('category');

        $this->assertSame([
            'cardinality' => ['field' => 'category'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_cardinality_aggregation_with_precision_threshold(): void
    {
        $agg = (new CardinalityAggregation('category'))->precisionThreshold(1000);

        $this->assertSame([
            'cardinality' => [
                'field' => 'category',
                'precision_threshold' => 1000,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_cardinality_aggregation_with_missing(): void
    {
        $agg = (new CardinalityAggregation('category'))->missing('N/A');

        $this->assertSame([
            'cardinality' => ['field' => 'category', 'missing' => 'N/A'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_cardinality_aggregation_with_script(): void
    {
        $agg = (new CardinalityAggregation('category'))->script(['source' => 'doc.category.value']);

        $this->assertSame([
            'cardinality' => [
                'field' => 'category',
                'script' => ['source' => 'doc.category.value'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_cardinality_aggregation_with_all_options(): void
    {
        $agg = (new CardinalityAggregation('category'))
            ->precisionThreshold(500)
            ->missing('unknown')
            ->script(['source' => 'doc.category.value']);

        $this->assertSame([
            'cardinality' => [
                'field' => 'category',
                'precision_threshold' => 500,
                'missing' => 'unknown',
                'script' => ['source' => 'doc.category.value'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new CardinalityAggregation('category');

        $this->assertSame($agg, $agg->precisionThreshold(1000));
        $this->assertSame($agg, $agg->missing('N/A'));
        $this->assertSame($agg, $agg->script(['source' => '_score']));
    }
}
