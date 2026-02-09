<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Compound;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Compound\DisMaxQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DisMaxQueryTest extends TestCase
{
    #[Test]
    public function it_throws_exception_for_empty_queries(): void
    {
        $query = new DisMaxQuery();

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('DisMaxQuery requires at least one query');

        $query->toArray();
    }

    // ---- Replacement method (queries) ----

    #[Test]
    public function it_builds_dis_max_query_with_queries_replacement(): void
    {
        $query = (new DisMaxQuery())
            ->queries(
                new TermQuery('status', 'active'),
                new TermQuery('type', 'post'),
            );

        $this->assertSame([
            'dis_max' => [
                'queries' => [
                    ['term' => ['status' => ['value' => 'active']]],
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_replaces_queries_completely(): void
    {
        $query = (new DisMaxQuery())
            ->queries(new TermQuery('status', 'active'))
            ->queries(new TermQuery('type', 'post'));

        $this->assertSame([
            'dis_max' => [
                'queries' => [
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $query->toArray());
    }

    // ---- Add method ----

    #[Test]
    public function it_adds_query_with_query_interface(): void
    {
        $query = (new DisMaxQuery())
            ->add(new TermQuery('status', 'active'))
            ->add(new TermQuery('type', 'post'));

        $this->assertSame([
            'dis_max' => [
                'queries' => [
                    ['term' => ['status' => ['value' => 'active']]],
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_adds_query_with_array(): void
    {
        $query = (new DisMaxQuery())
            ->add(['match' => ['title' => 'search']]);

        $this->assertSame([
            'dis_max' => [
                'queries' => [
                    ['match' => ['title' => 'search']],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function add_works_after_queries_replacement(): void
    {
        $query = (new DisMaxQuery())
            ->queries(new TermQuery('status', 'active'))
            ->add(new TermQuery('type', 'post'));

        $this->assertSame([
            'dis_max' => [
                'queries' => [
                    ['term' => ['status' => ['value' => 'active']]],
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function queries_clears_added_queries(): void
    {
        $query = (new DisMaxQuery())
            ->add(new TermQuery('status', 'active'))
            ->add(new TermQuery('type', 'post'))
            ->queries(new TermQuery('only', 'this'));

        $this->assertSame([
            'dis_max' => [
                'queries' => [
                    ['term' => ['only' => ['value' => 'this']]],
                ],
            ],
        ], $query->toArray());
    }

    // ---- Options ----

    #[Test]
    public function it_builds_dis_max_query_with_tie_breaker(): void
    {
        $query = (new DisMaxQuery())
            ->add(new TermQuery('status', 'active'))
            ->tieBreaker(0.7);

        $result = $query->toArray();
        $this->assertSame(0.7, $result['dis_max']['tie_breaker']);
    }

    #[Test]
    public function it_builds_dis_max_query_with_boost(): void
    {
        $query = (new DisMaxQuery())
            ->add(new TermQuery('status', 'active'))
            ->boost(1.5);

        $result = $query->toArray();
        $this->assertSame(1.5, $result['dis_max']['boost']);
    }

    #[Test]
    public function it_builds_dis_max_query_with_all_options(): void
    {
        $query = (new DisMaxQuery())
            ->queries(
                new TermQuery('status', 'active'),
                new TermQuery('type', 'post'),
            )
            ->tieBreaker(0.7)
            ->boost(1.2);

        $this->assertSame([
            'dis_max' => [
                'queries' => [
                    ['term' => ['status' => ['value' => 'active']]],
                    ['term' => ['type' => ['value' => 'post']]],
                ],
                'tie_breaker' => 0.7,
                'boost' => 1.2,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new DisMaxQuery();

        $this->assertSame($query, $query->queries(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->add(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->tieBreaker(0.5));
        $this->assertSame($query, $query->boost(1.0));
    }
}
