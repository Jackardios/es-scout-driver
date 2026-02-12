<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Bucket\ReverseNestedAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ReverseNestedAggregationTest extends TestCase
{
    #[Test]
    public function it_builds_basic_reverse_nested_aggregation(): void
    {
        $agg = new ReverseNestedAggregation();

        $result = $agg->toArray();

        $this->assertArrayHasKey('reverse_nested', $result);
        $this->assertInstanceOf(stdClass::class, $result['reverse_nested']);
    }

    #[Test]
    public function it_builds_reverse_nested_aggregation_with_path(): void
    {
        $agg = (new ReverseNestedAggregation())
            ->path('parent');

        $this->assertSame([
            'reverse_nested' => ['path' => 'parent'],
        ], $agg->toArray());
    }

    #[Test]
    public function it_builds_reverse_nested_aggregation_with_sub_aggregations(): void
    {
        $agg = (new ReverseNestedAggregation())
            ->agg('categories', new TermsAggregation('category'));

        $result = $agg->toArray();

        $this->assertArrayHasKey('reverse_nested', $result);
        $this->assertInstanceOf(stdClass::class, $result['reverse_nested']);
        $this->assertSame([
            'categories' => ['terms' => ['field' => 'category']],
        ], $result['aggs']);
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $agg = new ReverseNestedAggregation();

        $this->assertSame($agg, $agg->path('parent'));
        $this->assertSame($agg, $agg->agg('test', new TermsAggregation('field')));
    }
}
