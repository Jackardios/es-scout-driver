<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\NestedAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NestedAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_nested_aggregation(): void
    {
        $agg = new NestedAggregation('comments');

        $this->assertSame([
            'nested' => ['path' => 'comments'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_nested_aggregation_with_sub_aggregations(): void
    {
        $agg = (new NestedAggregation('comments'))
            ->agg('authors', new TermsAggregation('comments.author'));

        $this->assertSame([
            'nested' => ['path' => 'comments'],
            'aggs' => [
                'authors' => ['terms' => ['field' => 'comments.author']],
            ],
        ], $agg->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new NestedAggregation('comments');

        $this->assertSame($agg, $agg->agg('test', new TermsAggregation('field')));
    }
}
