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
    public function it_keeps_a_one_element_include_or_exclude_list_as_a_list_of_exact_values(): void
    {
        $agg = (new TermsAggregation('version'))
            ->include(['v1.0'])
            ->exclude([3]);

        $this->assertSame([
            'terms' => [
                'field' => 'version',
                'include' => ['v1.0'],
                'exclude' => [3],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_sends_an_include_or_exclude_string_as_a_regular_expression(): void
    {
        $agg = (new TermsAggregation('version'))
            ->include('v1.*')
            ->exclude('v1\\.0');

        $this->assertSame([
            'terms' => [
                'field' => 'version',
                'include' => 'v1.*',
                'exclude' => 'v1\\.0',
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

    #[Test]
    public function it_refuses_a_size_below_1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TermsAggregation size must be greater than 0.');

        (new TermsAggregation('category'))->size(0);
    }

    #[Test]
    public function it_refuses_a_sub_aggregation_name_that_would_make_the_sub_aggregations_a_list(): void
    {
        $agg = new TermsAggregation('category');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sub-aggregation name [0] would send the sub-aggregations as a JSON list');

        $agg->agg('0', new AvgAggregation('price'));
    }

    #[Test]
    public function it_keeps_integer_sub_aggregation_names_that_do_not_form_a_list(): void
    {
        $agg = (new TermsAggregation('category'))->agg('2024', new AvgAggregation('price'));

        $this->assertSame('{"terms":{"field":"category"},"aggs":{"2024":{"avg":{"field":"price"}}}}', json_encode($agg->toArray()));
    }
}
