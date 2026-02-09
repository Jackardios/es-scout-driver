<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AvgAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_avg_aggregation(): void
    {
        $agg = new AvgAggregation('price');

        $this->assertSame([
            'avg' => ['field' => 'price'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_avg_aggregation_with_missing(): void
    {
        $agg = (new AvgAggregation('price'))->missing('0');

        $this->assertSame([
            'avg' => ['field' => 'price', 'missing' => '0'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new AvgAggregation('price');

        $this->assertSame($agg, $agg->missing('0'));
        $this->assertSame($agg, $agg->script(['source' => '_score']));
    }
}
