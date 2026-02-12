<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\GlobalAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class GlobalAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_global_aggregation(): void
    {
        $agg = new GlobalAggregation();

        $result = $agg->toArray();

        $this->assertArrayHasKey('global', $result);
        $this->assertInstanceOf(stdClass::class, $result['global']);
    }

    #[Test]
    public function it_builds_global_aggregation_with_sub_aggregations(): void
    {
        $agg = (new GlobalAggregation())
            ->agg('avg_price', new AvgAggregation('price'));

        $result = $agg->toArray();

        $this->assertArrayHasKey('global', $result);
        $this->assertInstanceOf(stdClass::class, $result['global']);
        $this->assertSame([
            'avg_price' => ['avg' => ['field' => 'price']],
        ], $result['aggs']);
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new GlobalAggregation();

        $this->assertSame($agg, $agg->agg('test', new AvgAggregation('price')));
    }
}
