<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\RangeAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RangeAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_range_aggregation_with_ranges_array(): void
    {
        $agg = (new RangeAggregation('price'))
            ->ranges([
                ['to' => 50],
                ['from' => 50, 'to' => 100],
                ['from' => 100],
            ]);

        $this->assertSame([
            'range' => [
                'field' => 'price',
                'ranges' => [
                    ['to' => 50],
                    ['from' => 50, 'to' => 100],
                    ['from' => 100],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_range_aggregation_with_fluent_ranges(): void
    {
        $agg = (new RangeAggregation('price'))
            ->range(to: 50, key: 'cheap')
            ->range(from: 50, to: 100, key: 'moderate')
            ->range(from: 100, key: 'expensive');

        $this->assertSame([
            'range' => [
                'field' => 'price',
                'ranges' => [
                    ['to' => 50, 'key' => 'cheap'],
                    ['from' => 50, 'to' => 100, 'key' => 'moderate'],
                    ['from' => 100, 'key' => 'expensive'],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_range_aggregation_with_keyed(): void
    {
        $agg = (new RangeAggregation('price'))
            ->ranges([['to' => 50], ['from' => 50]])
            ->keyed();

        $this->assertSame([
            'range' => [
                'field' => 'price',
                'ranges' => [['to' => 50], ['from' => 50]],
                'keyed' => true,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new RangeAggregation('price');

        $this->assertSame($agg, $agg->ranges([]));
        $this->assertSame($agg, $agg->range(from: 0));
        $this->assertSame($agg, $agg->keyed());
    }
}
