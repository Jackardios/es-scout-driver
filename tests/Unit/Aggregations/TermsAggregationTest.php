<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TermsAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_terms_aggregation(): void
    {
        $agg = new TermsAggregation('author');

        $this->assertSame([
            'terms' => ['field' => 'author'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_terms_aggregation_with_size(): void
    {
        $agg = (new TermsAggregation('author'))->size(10);

        $this->assertSame([
            'terms' => ['field' => 'author', 'size' => 10],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_terms_aggregation_with_all_options(): void
    {
        $agg = (new TermsAggregation('author'))
            ->size(10)
            ->minDocCount(2)
            ->shardSize(50)
            ->orderByCount('desc')
            ->missing('Unknown');

        $this->assertSame([
            'terms' => [
                'field' => 'author',
                'size' => 10,
                'min_doc_count' => 2,
                'shard_size' => 50,
                'order' => ['_count' => 'desc'],
                'missing' => 'Unknown',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_terms_aggregation_with_sub_aggregations(): void
    {
        $agg = (new TermsAggregation('author'))
            ->size(10)
            ->agg('avg_price', new AvgAggregation('price'));

        $this->assertSame([
            'terms' => ['field' => 'author', 'size' => 10],
            'aggs' => [
                'avg_price' => ['avg' => ['field' => 'price']],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_terms_aggregation_with_include_exclude(): void
    {
        $agg = (new TermsAggregation('author'))
            ->include(['John*', 'Jane*'])
            ->exclude('Anonymous');

        $this->assertSame([
            'terms' => [
                'field' => 'author',
                'include' => ['John*', 'Jane*'],
                'exclude' => 'Anonymous',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new TermsAggregation('author');

        $this->assertSame($agg, $agg->size(10));
        $this->assertSame($agg, $agg->minDocCount(1));
        $this->assertSame($agg, $agg->orderByKey());
        $this->assertSame($agg, $agg->agg('test', ['avg' => ['field' => 'price']]));
    }
}
