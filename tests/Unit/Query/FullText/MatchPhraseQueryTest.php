<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\FullText;

use Jackardios\EsScoutDriver\Query\FullText\MatchPhraseQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MatchPhraseQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_match_phrase_query(): void
    {
        $query = new MatchPhraseQuery('title', 'quick brown fox');

        $this->assertSame([
            'match_phrase' => ['title' => ['query' => 'quick brown fox']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_match_phrase_query_with_all_options(): void
    {
        $query = (new MatchPhraseQuery('title', 'quick brown fox'))
            ->analyzer('standard')
            ->slop(2)
            ->zeroTermsQuery('all');

        $this->assertSame([
            'match_phrase' => ['title' => [
                'query' => 'quick brown fox',
                'analyzer' => 'standard',
                'slop' => 2,
                'zero_terms_query' => 'all',
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new MatchPhraseQuery('title', 'quick brown fox');

        $this->assertSame($query, $query->analyzer('standard'));
        $this->assertSame($query, $query->slop(2));
        $this->assertSame($query, $query->zeroTermsQuery('all'));
    }
}
