<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Compound;

use Jackardios\EsScoutDriver\Query\Compound\BoostingQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BoostingQueryTest extends TestCase
{
    #[Test]
    public function it_builds_boosting_query_with_query_interfaces(): void
    {
        $query = new BoostingQuery(
            new TermQuery('status', 'active'),
            new TermQuery('status', 'deleted')
        );

        $this->assertSame([
            'boosting' => [
                'positive' => ['term' => ['status' => ['value' => 'active']]],
                'negative' => ['term' => ['status' => ['value' => 'deleted']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_boosting_query_with_array_queries(): void
    {
        $query = new BoostingQuery(
            ['match' => ['title' => 'search']],
            ['match' => ['body' => 'spam']]
        );

        $this->assertSame([
            'boosting' => [
                'positive' => ['match' => ['title' => 'search']],
                'negative' => ['match' => ['body' => 'spam']],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_boosting_query_with_negative_boost(): void
    {
        $query = (new BoostingQuery(
            new TermQuery('status', 'active'),
            new TermQuery('status', 'spam')
        ))->negativeBoost(0.5);

        $this->assertSame([
            'boosting' => [
                'positive' => ['term' => ['status' => ['value' => 'active']]],
                'negative' => ['term' => ['status' => ['value' => 'spam']]],
                'negative_boost' => 0.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_boosting_query_with_all_options(): void
    {
        $query = (new BoostingQuery(
            new MatchAllQuery(),
            new TermQuery('status', 'spam')
        ))->negativeBoost(0.2);

        $result = $query->toArray();
        $this->assertArrayHasKey('match_all', $result['boosting']['positive']);
        $this->assertSame(
            ['term' => ['status' => ['value' => 'spam']]],
            $result['boosting']['negative']
        );
        $this->assertSame(0.2, $result['boosting']['negative_boost']);
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new BoostingQuery(
            new TermQuery('a', 'b'),
            new TermQuery('a', 'b')
        );

        $this->assertSame($query, $query->negativeBoost(0.5));
    }
}
