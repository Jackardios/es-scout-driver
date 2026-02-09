<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Compound;

use Jackardios\EsScoutDriver\Query\Compound\FunctionScoreQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FunctionScoreQueryTest extends TestCase
{
    #[Test]
    public function it_builds_empty_function_score_query(): void
    {
        $query = new FunctionScoreQuery();

        $this->assertSame(['function_score' => []], $query->toArray());
    }

    #[Test]
    public function it_builds_function_score_query_with_query_interface(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(new MatchAllQuery());

        $result = $query->toArray();
        $this->assertArrayHasKey('query', $result['function_score']);
        $this->assertArrayHasKey('match_all', $result['function_score']['query']);
    }

    #[Test]
    public function it_builds_function_score_query_with_array_query(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match' => ['title' => 'search']]);

        $this->assertSame([
            'function_score' => [
                'query' => ['match' => ['title' => 'search']],
            ],
        ], $query->toArray());
    }

    // ---- Replacement method (functions) ----

    #[Test]
    public function it_builds_function_score_query_with_functions_replacement(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->functions(
                ['weight' => 2, 'filter' => ['term' => ['status' => 'published']]],
                ['random_score' => ['seed' => 123, 'field' => '_seq_no']],
            );

        $this->assertSame([
            'function_score' => [
                'query' => ['match_all' => []],
                'functions' => [
                    ['weight' => 2, 'filter' => ['term' => ['status' => 'published']]],
                    ['random_score' => ['seed' => 123, 'field' => '_seq_no']],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_replaces_functions_completely(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->functions(['weight' => 2])
            ->functions(['weight' => 5]);

        $this->assertSame([
            'function_score' => [
                'query' => ['match_all' => []],
                'functions' => [
                    ['weight' => 5],
                ],
            ],
        ], $query->toArray());
    }

    // ---- Add method ----

    #[Test]
    public function it_adds_functions_with_add_function(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->addFunction(['weight' => 2, 'filter' => ['term' => ['status' => 'published']]])
            ->addFunction(['random_score' => ['seed' => 123, 'field' => '_seq_no']]);

        $this->assertSame([
            'function_score' => [
                'query' => ['match_all' => []],
                'functions' => [
                    ['weight' => 2, 'filter' => ['term' => ['status' => 'published']]],
                    ['random_score' => ['seed' => 123, 'field' => '_seq_no']],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function add_function_works_after_functions_replacement(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->functions(['weight' => 2])
            ->addFunction(['weight' => 5]);

        $this->assertSame([
            'function_score' => [
                'query' => ['match_all' => []],
                'functions' => [
                    ['weight' => 2],
                    ['weight' => 5],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function functions_clears_added_functions(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->addFunction(['weight' => 2])
            ->addFunction(['weight' => 3])
            ->functions(['weight' => 10]);

        $this->assertSame([
            'function_score' => [
                'query' => ['match_all' => []],
                'functions' => [
                    ['weight' => 10],
                ],
            ],
        ], $query->toArray());
    }

    // ---- Options ----

    #[Test]
    public function it_builds_function_score_query_with_score_mode(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->addFunction(['weight' => 2])
            ->scoreMode('multiply');

        $result = $query->toArray();
        $this->assertSame('multiply', $result['function_score']['score_mode']);
    }

    #[Test]
    public function it_builds_function_score_query_with_boost_mode(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->boostMode('replace');

        $result = $query->toArray();
        $this->assertSame('replace', $result['function_score']['boost_mode']);
    }

    #[Test]
    public function it_builds_function_score_query_with_max_boost(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->maxBoost(42.0);

        $result = $query->toArray();
        $this->assertSame(42.0, $result['function_score']['max_boost']);
    }

    #[Test]
    public function it_builds_function_score_query_with_min_score(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->minScore(5.0);

        $result = $query->toArray();
        $this->assertSame(5.0, $result['function_score']['min_score']);
    }

    #[Test]
    public function it_builds_function_score_query_with_all_options(): void
    {
        $query = (new FunctionScoreQuery())
            ->query(['match_all' => []])
            ->functions(['weight' => 2])
            ->scoreMode('sum')
            ->boostMode('multiply')
            ->maxBoost(10.0)
            ->minScore(2.0)
            ->boost(1.5);

        $this->assertSame([
            'function_score' => [
                'query' => ['match_all' => []],
                'functions' => [
                    ['weight' => 2],
                ],
                'score_mode' => 'sum',
                'boost_mode' => 'multiply',
                'max_boost' => 10.0,
                'min_score' => 2.0,
                'boost' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new FunctionScoreQuery();

        $this->assertSame($query, $query->query(['match_all' => []]));
        $this->assertSame($query, $query->functions(['weight' => 1]));
        $this->assertSame($query, $query->addFunction(['weight' => 1]));
        $this->assertSame($query, $query->scoreMode('sum'));
        $this->assertSame($query, $query->boostMode('multiply'));
        $this->assertSame($query, $query->maxBoost(10.0));
        $this->assertSame($query, $query->minScore(1.0));
        $this->assertSame($query, $query->boost(1.0));
    }
}
