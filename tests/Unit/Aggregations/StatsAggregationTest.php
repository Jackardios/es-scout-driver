<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\StatsAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatsAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_stats_aggregation(): void
    {
        $agg = new StatsAggregation('price');

        $this->assertSame([
            'stats' => ['field' => 'price'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_stats_aggregation_with_missing(): void
    {
        $agg = (new StatsAggregation('price'))->missing('0');

        $this->assertSame([
            'stats' => ['field' => 'price', 'missing' => '0'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_stats_aggregation_with_script(): void
    {
        $agg = (new StatsAggregation('price'))->script(['source' => 'doc.price.value']);

        $this->assertSame([
            'stats' => [
                'field' => 'price',
                'script' => ['source' => 'doc.price.value'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new StatsAggregation('price');

        $this->assertSame($agg, $agg->missing('0'));
        $this->assertSame($agg, $agg->script(['source' => '_score']));
    }
}
