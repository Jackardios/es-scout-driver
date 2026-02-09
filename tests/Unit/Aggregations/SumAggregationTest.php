<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\SumAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SumAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_sum_aggregation(): void
    {
        $agg = new SumAggregation('price');

        $this->assertSame([
            'sum' => ['field' => 'price'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_sum_aggregation_with_missing(): void
    {
        $agg = (new SumAggregation('price'))->missing('0');

        $this->assertSame([
            'sum' => ['field' => 'price', 'missing' => '0'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_sum_aggregation_with_script(): void
    {
        $agg = (new SumAggregation('price'))->script(['source' => 'doc.price.value * 2']);

        $this->assertSame([
            'sum' => [
                'field' => 'price',
                'script' => ['source' => 'doc.price.value * 2'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new SumAggregation('price');

        $this->assertSame($agg, $agg->missing('0'));
        $this->assertSame($agg, $agg->script(['source' => '_score']));
    }
}
