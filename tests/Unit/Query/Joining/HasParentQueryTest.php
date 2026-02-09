<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Joining;

use Jackardios\EsScoutDriver\Query\Joining\HasParentQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasParentQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_has_parent_query_with_query_interface(): void
    {
        $query = new HasParentQuery('post', new TermQuery('status', 'published'));

        $this->assertSame([
            'has_parent' => [
                'parent_type' => 'post',
                'query' => ['term' => ['status' => ['value' => 'published']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_parent_query_with_array_query(): void
    {
        $query = new HasParentQuery('post', ['match' => ['title' => 'elasticsearch']]);

        $this->assertSame([
            'has_parent' => [
                'parent_type' => 'post',
                'query' => ['match' => ['title' => 'elasticsearch']],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_parent_query_with_closure(): void
    {
        $query = new HasParentQuery('post', fn() => new TermQuery('status', 'published'));

        $this->assertSame([
            'has_parent' => [
                'parent_type' => 'post',
                'query' => ['term' => ['status' => ['value' => 'published']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_parent_query_with_score_true(): void
    {
        $query = (new HasParentQuery('post', new TermQuery('status', 'published')))
            ->score(true);

        $this->assertSame([
            'has_parent' => [
                'parent_type' => 'post',
                'query' => ['term' => ['status' => ['value' => 'published']]],
                'score' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_parent_query_with_score_false(): void
    {
        $query = (new HasParentQuery('post', new TermQuery('status', 'published')))
            ->score(false);

        $this->assertSame([
            'has_parent' => [
                'parent_type' => 'post',
                'query' => ['term' => ['status' => ['value' => 'published']]],
                'score' => false,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_parent_query_with_ignore_unmapped(): void
    {
        $query = (new HasParentQuery('post', new TermQuery('status', 'published')))
            ->ignoreUnmapped(true);

        $this->assertSame([
            'has_parent' => [
                'parent_type' => 'post',
                'query' => ['term' => ['status' => ['value' => 'published']]],
                'ignore_unmapped' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_parent_query_with_inner_hits_empty(): void
    {
        $query = (new HasParentQuery('post', new TermQuery('status', 'published')))
            ->innerHits();

        $result = $query->toArray();
        $this->assertSame('post', $result['has_parent']['parent_type']);
        $this->assertArrayHasKey('inner_hits', $result['has_parent']);
        $this->assertInstanceOf(\stdClass::class, $result['has_parent']['inner_hits']);
    }

    #[Test]
    public function it_builds_has_parent_query_with_inner_hits_options(): void
    {
        $query = (new HasParentQuery('post', new TermQuery('status', 'published')))
            ->innerHits(['size' => 3, 'name' => 'parent_post']);

        $this->assertSame([
            'has_parent' => [
                'parent_type' => 'post',
                'query' => ['term' => ['status' => ['value' => 'published']]],
                'inner_hits' => ['size' => 3, 'name' => 'parent_post'],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_parent_query_with_all_options(): void
    {
        $query = (new HasParentQuery('post', new TermQuery('status', 'published')))
            ->score(true)
            ->ignoreUnmapped(true)
            ->innerHits(['size' => 1]);

        $this->assertSame([
            'has_parent' => [
                'parent_type' => 'post',
                'query' => ['term' => ['status' => ['value' => 'published']]],
                'score' => true,
                'ignore_unmapped' => true,
                'inner_hits' => ['size' => 1],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new HasParentQuery('post', new TermQuery('status', 'published'));

        $this->assertSame($query, $query->score(true));
        $this->assertSame($query, $query->ignoreUnmapped(true));
        $this->assertSame($query, $query->innerHits());
    }
}
