<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\MinAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MinAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_min_aggregation(): void
    {
        $agg = new MinAggregation('price');

        $this->assertSame([
            'min' => ['field' => 'price'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_min_aggregation_with_missing(): void
    {
        $agg = (new MinAggregation('price'))->missing('0');

        $this->assertSame([
            'min' => ['field' => 'price', 'missing' => '0'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_min_aggregation_with_script(): void
    {
        $agg = (new MinAggregation('price'))->script(['source' => 'doc.price.value * 0.9']);

        $this->assertSame([
            'min' => [
                'field' => 'price',
                'script' => ['source' => 'doc.price.value * 0.9'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new MinAggregation('price');

        $this->assertSame($agg, $agg->missing('0'));
        $this->assertSame($agg, $agg->script(['source' => '_score']));
    }
}
