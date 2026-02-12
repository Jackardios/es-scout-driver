<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\ExtendedStatsAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtendedStatsAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_extended_stats_aggregation(): void
    {
        $agg = new ExtendedStatsAggregation('price');

        $this->assertSame([
            'extended_stats' => ['field' => 'price'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_extended_stats_aggregation_with_sigma(): void
    {
        $agg = (new ExtendedStatsAggregation('price'))
            ->sigma(2.0);

        $this->assertSame([
            'extended_stats' => [
                'field' => 'price',
                'sigma' => 2.0,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_extended_stats_aggregation_with_missing(): void
    {
        $agg = (new ExtendedStatsAggregation('price'))
            ->missing('0');

        $this->assertSame([
            'extended_stats' => [
                'field' => 'price',
                'missing' => '0',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_extended_stats_aggregation_with_script(): void
    {
        $agg = (new ExtendedStatsAggregation('price'))
            ->script(['source' => 'doc.price.value']);

        $this->assertSame([
            'extended_stats' => [
                'field' => 'price',
                'script' => ['source' => 'doc.price.value'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new ExtendedStatsAggregation('price');

        $this->assertSame($agg, $agg->sigma(2.0));
        $this->assertSame($agg, $agg->missing('0'));
        $this->assertSame($agg, $agg->script(['source' => '_score']));
    }
}
