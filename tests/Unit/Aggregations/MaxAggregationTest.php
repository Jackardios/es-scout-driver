<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\MaxAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MaxAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_max_aggregation(): void
    {
        $agg = new MaxAggregation('price');

        $this->assertSame([
            'max' => ['field' => 'price'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_max_aggregation_with_missing(): void
    {
        $agg = (new MaxAggregation('price'))->missing('0');

        $this->assertSame([
            'max' => ['field' => 'price', 'missing' => '0'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_max_aggregation_with_script(): void
    {
        $agg = (new MaxAggregation('price'))->script(['source' => 'doc.price.value * 1.1']);

        $this->assertSame([
            'max' => [
                'field' => 'price',
                'script' => ['source' => 'doc.price.value * 1.1'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new MaxAggregation('price');

        $this->assertSame($agg, $agg->missing('0'));
        $this->assertSame($agg, $agg->script(['source' => '_score']));
    }
}
