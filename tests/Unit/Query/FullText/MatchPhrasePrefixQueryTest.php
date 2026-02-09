<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\FullText;

use Jackardios\EsScoutDriver\Query\FullText\MatchPhrasePrefixQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MatchPhrasePrefixQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_match_phrase_prefix_query(): void
    {
        $query = new MatchPhrasePrefixQuery('title', 'quick brown f');

        $this->assertSame([
            'match_phrase_prefix' => ['title' => ['query' => 'quick brown f']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_match_phrase_prefix_query_with_all_options(): void
    {
        $query = (new MatchPhrasePrefixQuery('title', 'quick brown f'))
            ->analyzer('standard')
            ->maxExpansions(10)
            ->slop(2)
            ->zeroTermsQuery('none');

        $this->assertSame([
            'match_phrase_prefix' => ['title' => [
                'query' => 'quick brown f',
                'analyzer' => 'standard',
                'max_expansions' => 10,
                'slop' => 2,
                'zero_terms_query' => 'none',
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new MatchPhrasePrefixQuery('title', 'quick brown f');

        $this->assertSame($query, $query->analyzer('standard'));
        $this->assertSame($query, $query->maxExpansions(10));
        $this->assertSame($query, $query->slop(2));
        $this->assertSame($query, $query->zeroTermsQuery('none'));
    }
}
