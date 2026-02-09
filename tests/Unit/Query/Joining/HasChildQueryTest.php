<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Joining;

use Jackardios\EsScoutDriver\Enums\ScoreMode;
use Jackardios\EsScoutDriver\Query\Joining\HasChildQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasChildQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_has_child_query_with_query_interface(): void
    {
        $query = new HasChildQuery('comment', new TermQuery('status', 'approved'));

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_array_query(): void
    {
        $query = new HasChildQuery('comment', ['match' => ['body' => 'great']]);

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['match' => ['body' => 'great']],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_closure(): void
    {
        $query = new HasChildQuery('comment', fn() => new TermQuery('status', 'approved'));

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_score_mode_string(): void
    {
        $query = (new HasChildQuery('comment', new TermQuery('status', 'approved')))
            ->scoreMode('avg');

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
                'score_mode' => 'avg',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_score_mode_enum(): void
    {
        $query = (new HasChildQuery('comment', new TermQuery('status', 'approved')))
            ->scoreMode(ScoreMode::Max);

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
                'score_mode' => 'max',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_min_children(): void
    {
        $query = (new HasChildQuery('comment', new TermQuery('status', 'approved')))
            ->minChildren(2);

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
                'min_children' => 2,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_max_children(): void
    {
        $query = (new HasChildQuery('comment', new TermQuery('status', 'approved')))
            ->maxChildren(10);

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
                'max_children' => 10,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_ignore_unmapped(): void
    {
        $query = (new HasChildQuery('comment', new TermQuery('status', 'approved')))
            ->ignoreUnmapped(true);

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
                'ignore_unmapped' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_inner_hits_empty(): void
    {
        $query = (new HasChildQuery('comment', new TermQuery('status', 'approved')))
            ->innerHits();

        $result = $query->toArray();
        $this->assertSame('comment', $result['has_child']['type']);
        $this->assertArrayHasKey('inner_hits', $result['has_child']);
        $this->assertInstanceOf(\stdClass::class, $result['has_child']['inner_hits']);
    }

    #[Test]
    public function it_builds_has_child_query_with_inner_hits_options(): void
    {
        $query = (new HasChildQuery('comment', new TermQuery('status', 'approved')))
            ->innerHits(['size' => 5, 'name' => 'approved_comments']);

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
                'inner_hits' => ['size' => 5, 'name' => 'approved_comments'],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_has_child_query_with_all_options(): void
    {
        $query = (new HasChildQuery('comment', new TermQuery('status', 'approved')))
            ->scoreMode(ScoreMode::Sum)
            ->minChildren(1)
            ->maxChildren(5)
            ->ignoreUnmapped(true)
            ->innerHits(['size' => 3]);

        $this->assertSame([
            'has_child' => [
                'type' => 'comment',
                'query' => ['term' => ['status' => ['value' => 'approved']]],
                'score_mode' => 'sum',
                'min_children' => 1,
                'max_children' => 5,
                'ignore_unmapped' => true,
                'inner_hits' => ['size' => 3],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new HasChildQuery('comment', new TermQuery('status', 'approved'));

        $this->assertSame($query, $query->scoreMode('avg'));
        $this->assertSame($query, $query->minChildren(1));
        $this->assertSame($query, $query->maxChildren(10));
        $this->assertSame($query, $query->ignoreUnmapped(true));
        $this->assertSame($query, $query->innerHits());
    }

    #[Test]
    public function it_deep_clones_query_interface(): void
    {
        $innerQuery = new TermQuery('status', 'approved');
        $query = new HasChildQuery('comment', $innerQuery);
        $cloned = clone $query;

        $this->assertEquals($query->toArray(), $cloned->toArray());
        $this->assertNotSame($query, $cloned);

        $reflection = new \ReflectionClass($query);
        $prop = $reflection->getProperty('query');
        $prop->setAccessible(true);

        $originalInner = $prop->getValue($query);
        $clonedInner = $prop->getValue($cloned);

        $this->assertNotSame($originalInner, $clonedInner);
    }

    #[Test]
    public function it_does_not_clone_array_query(): void
    {
        $query = new HasChildQuery('comment', ['match' => ['body' => 'test']]);
        $cloned = clone $query;

        $this->assertEquals($query->toArray(), $cloned->toArray());
    }
}
