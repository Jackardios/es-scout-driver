<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Compound;

use Jackardios\EsScoutDriver\Query\Compound\NestedQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NestedQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_nested_query_with_query_interface(): void
    {
        $query = new NestedQuery('comments', new TermQuery('comments.status', 'approved'));

        $this->assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['comments.status' => ['value' => 'approved']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_nested_query_with_array_query(): void
    {
        $query = new NestedQuery('comments', ['match' => ['comments.body' => 'great']]);

        $this->assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['match' => ['comments.body' => 'great']],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_nested_query_with_closure(): void
    {
        $query = new NestedQuery('comments', fn() => new TermQuery('comments.status', 'approved'));

        $this->assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['comments.status' => ['value' => 'approved']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_nested_query_with_score_mode(): void
    {
        $query = (new NestedQuery('comments', new TermQuery('comments.status', 'approved')))
            ->scoreMode('avg');

        $this->assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['comments.status' => ['value' => 'approved']]],
                'score_mode' => 'avg',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_nested_query_with_ignore_unmapped(): void
    {
        $query = (new NestedQuery('comments', new TermQuery('comments.status', 'approved')))
            ->ignoreUnmapped(true);

        $this->assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['comments.status' => ['value' => 'approved']]],
                'ignore_unmapped' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_nested_query_with_inner_hits_empty(): void
    {
        $query = (new NestedQuery('comments', new TermQuery('comments.status', 'approved')))
            ->innerHits();

        $result = $query->toArray();
        $this->assertSame('comments', $result['nested']['path']);
        $this->assertArrayHasKey('inner_hits', $result['nested']);
        $this->assertInstanceOf(\stdClass::class, $result['nested']['inner_hits']);
    }

    #[Test]
    public function it_builds_nested_query_with_inner_hits_options(): void
    {
        $query = (new NestedQuery('comments', new TermQuery('comments.status', 'approved')))
            ->innerHits(['size' => 5, 'name' => 'approved_comments']);

        $this->assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['comments.status' => ['value' => 'approved']]],
                'inner_hits' => ['size' => 5, 'name' => 'approved_comments'],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_nested_query_with_all_options(): void
    {
        $query = (new NestedQuery('comments', new TermQuery('comments.status', 'approved')))
            ->scoreMode('max')
            ->ignoreUnmapped(true)
            ->innerHits(['size' => 3]);

        $this->assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['term' => ['comments.status' => ['value' => 'approved']]],
                'score_mode' => 'max',
                'ignore_unmapped' => true,
                'inner_hits' => ['size' => 3],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new NestedQuery('comments', new TermQuery('comments.status', 'approved'));

        $this->assertSame($query, $query->scoreMode('avg'));
        $this->assertSame($query, $query->ignoreUnmapped(true));
        $this->assertSame($query, $query->innerHits());
    }
}
