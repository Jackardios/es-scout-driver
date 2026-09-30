<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Aggregations\Bucket\FiltersAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FiltersAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_filters_aggregation_with_fluent_api(): void
    {
        $agg = (new FiltersAggregation())
            ->filter('published', new TermQuery('status', 'published'))
            ->filter('draft', new TermQuery('status', 'draft'));

        $this->assertSame([
            'filters' => [
                'filters' => [
                    'published' => ['term' => ['status' => ['value' => 'published']]],
                    'draft' => ['term' => ['status' => ['value' => 'draft']]],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_filters_aggregation_with_array_filters(): void
    {
        $agg = (new FiltersAggregation())
            ->filters([
                'published' => ['term' => ['status' => 'published']],
                'draft' => ['term' => ['status' => 'draft']],
            ]);

        $this->assertSame([
            'filters' => [
                'filters' => [
                    'published' => ['term' => ['status' => 'published']],
                    'draft' => ['term' => ['status' => 'draft']],
                ],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_filters_aggregation_with_other_bucket(): void
    {
        $agg = (new FiltersAggregation())
            ->filter('published', new TermQuery('status', 'published'))
            ->otherBucket();

        $this->assertSame([
            'filters' => [
                'filters' => [
                    'published' => ['term' => ['status' => ['value' => 'published']]],
                ],
                'other_bucket' => true,
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_filters_aggregation_with_other_bucket_key(): void
    {
        $agg = (new FiltersAggregation())
            ->filter('published', new TermQuery('status', 'published'))
            ->otherBucket()
            ->otherBucketKey('other_statuses');

        $this->assertSame([
            'filters' => [
                'filters' => [
                    'published' => ['term' => ['status' => ['value' => 'published']]],
                ],
                'other_bucket' => true,
                'other_bucket_key' => 'other_statuses',
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_filters_aggregation_with_sub_aggregations(): void
    {
        $agg = (new FiltersAggregation())
            ->filter('published', new TermQuery('status', 'published'))
            ->agg('avg_price', new AvgAggregation('price'));

        $this->assertSame([
            'filters' => [
                'filters' => [
                    'published' => ['term' => ['status' => ['value' => 'published']]],
                ],
            ],
            'aggs' => [
                'avg_price' => ['avg' => ['field' => 'price']],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_throws_exception_for_empty_filters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FiltersAggregation requires at least one filter.');

        (new FiltersAggregation())->toArray();
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new FiltersAggregation();

        $this->assertSame($agg, $agg->filter('test', ['term' => ['field' => 'value']]));
        $this->assertSame($agg, $agg->filters(['test' => ['term' => ['field' => 'value']]]));
        $this->assertSame($agg, $agg->otherBucket());
        $this->assertSame($agg, $agg->otherBucketKey('other'));
        $this->assertSame($agg, $agg->agg('avg', new AvgAggregation('price')));
    }

    #[Test]
    public function it_refuses_a_filter_name_that_would_make_the_filters_an_anonymous_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FiltersAggregation filter name [0] would send the filters as an anonymous list');

        (new FiltersAggregation())->filter('0', ['match_all' => new \stdClass()]);
    }

    #[Test]
    public function it_keeps_integer_filter_names_that_do_not_form_a_list(): void
    {
        $agg = (new FiltersAggregation())
            ->filter('2024', ['term' => ['year' => 2024]])
            ->filter('0', ['term' => ['year' => 0]]);

        $this->assertSame(
            '{"filters":{"filters":{"2024":{"term":{"year":2024}},"0":{"term":{"year":0}}}}}',
            json_encode($agg->toArray()),
        );
    }
}
