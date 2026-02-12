<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\PercentilesAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PercentilesAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_percentiles_aggregation(): void
    {
        $agg = new PercentilesAggregation('load_time');

        $this->assertSame([
            'percentiles' => ['field' => 'load_time'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_percentiles_aggregation_with_custom_percents(): void
    {
        $agg = (new PercentilesAggregation('load_time'))
            ->percents([50, 90, 99]);

        $this->assertSame([
            'percentiles' => [
                'field' => 'load_time',
                'percents' => [50, 90, 99],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_percentiles_aggregation_with_compression(): void
    {
        $agg = (new PercentilesAggregation('load_time'))
            ->compression(200);

        $this->assertSame([
            'percentiles' => [
                'field' => 'load_time',
                'tdigest' => ['compression' => 200],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_percentiles_aggregation_with_keyed(): void
    {
        $agg = (new PercentilesAggregation('load_time'))
            ->keyed(false);

        $this->assertSame([
            'percentiles' => [
                'field' => 'load_time',
                'keyed' => false,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_percentiles_aggregation_with_missing(): void
    {
        $agg = (new PercentilesAggregation('load_time'))
            ->missing('0');

        $this->assertSame([
            'percentiles' => [
                'field' => 'load_time',
                'missing' => '0',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_percentiles_aggregation_with_script(): void
    {
        $agg = (new PercentilesAggregation('load_time'))
            ->script(['source' => 'doc.load_time.value']);

        $this->assertSame([
            'percentiles' => [
                'field' => 'load_time',
                'script' => ['source' => 'doc.load_time.value'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new PercentilesAggregation('load_time');

        $this->assertSame($agg, $agg->percents([50, 90, 99]));
        $this->assertSame($agg, $agg->compression(200));
        $this->assertSame($agg, $agg->keyed(false));
        $this->assertSame($agg, $agg->missing('0'));
        $this->assertSame($agg, $agg->script(['source' => '_score']));
    }
}
