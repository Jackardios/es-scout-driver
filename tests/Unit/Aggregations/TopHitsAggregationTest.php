<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Metric\TopHitsAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TopHitsAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_top_hits_aggregation(): void
    {
        $agg = new TopHitsAggregation();

        $this->assertSame([
            'top_hits' => [],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_size(): void
    {
        $agg = (new TopHitsAggregation())->size(5);

        $this->assertSame([
            'top_hits' => ['size' => 5],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_from(): void
    {
        $agg = (new TopHitsAggregation())->from(10);

        $this->assertSame([
            'top_hits' => ['from' => 10],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_sort(): void
    {
        $agg = (new TopHitsAggregation())
            ->sort('created_at', 'desc')
            ->sort('_score', 'desc');

        $this->assertSame([
            'top_hits' => [
                'sort' => [
                    ['created_at' => ['order' => 'desc']],
                    ['_score' => ['order' => 'desc']],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_sort_raw(): void
    {
        $agg = (new TopHitsAggregation())
            ->sortRaw([['created_at' => 'desc']]);

        $this->assertSame([
            'top_hits' => [
                'sort' => [['created_at' => 'desc']],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_source_array(): void
    {
        $agg = (new TopHitsAggregation())
            ->source(['title', 'description']);

        $this->assertSame([
            'top_hits' => [
                '_source' => ['title', 'description'],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_source_false(): void
    {
        $agg = (new TopHitsAggregation())->source(false);

        $this->assertSame([
            'top_hits' => ['_source' => false],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_highlight(): void
    {
        $agg = (new TopHitsAggregation())
            ->highlight(['fields' => ['title' => new \stdClass()]]);

        $this->assertEquals([
            'top_hits' => [
                'highlight' => ['fields' => ['title' => new \stdClass()]],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_explain(): void
    {
        $agg = (new TopHitsAggregation())->explain();

        $this->assertSame([
            'top_hits' => ['explain' => true],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_top_hits_aggregation_with_version(): void
    {
        $agg = (new TopHitsAggregation())->version();

        $this->assertSame([
            'top_hits' => ['version' => true],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_full_top_hits_aggregation(): void
    {
        $agg = (new TopHitsAggregation())
            ->size(3)
            ->from(0)
            ->sort('date', 'desc')
            ->source(['title', 'date'])
            ->explain(false)
            ->version(false);

        $this->assertSame([
            'top_hits' => [
                'size' => 3,
                'from' => 0,
                'sort' => [['date' => ['order' => 'desc']]],
                '_source' => ['title', 'date'],
                'explain' => false,
                'version' => false,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new TopHitsAggregation();

        $this->assertSame($agg, $agg->size(5));
        $this->assertSame($agg, $agg->from(0));
        $this->assertSame($agg, $agg->sort('date', 'desc'));
        $this->assertSame($agg, $agg->sortRaw([]));
        $this->assertSame($agg, $agg->source(['title']));
        $this->assertSame($agg, $agg->highlight([]));
        $this->assertSame($agg, $agg->explain());
        $this->assertSame($agg, $agg->version());
    }
}
