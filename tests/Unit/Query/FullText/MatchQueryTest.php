<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\FullText;

use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MatchQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_match_query(): void
    {
        $query = new MatchQuery('title', 'search text');

        $this->assertSame([
            'match' => ['title' => ['query' => 'search text']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_match_query_with_common_options(): void
    {
        $query = (new MatchQuery('title', 'search text'))
            ->analyzer('standard')
            ->fuzziness('AUTO')
            ->operator('and')
            ->minimumShouldMatch('75%')
            ->boost(1.5);

        $this->assertSame([
            'match' => ['title' => [
                'query' => 'search text',
                'analyzer' => 'standard',
                'fuzziness' => 'AUTO',
                'operator' => 'and',
                'minimum_should_match' => '75%',
                'boost' => 1.5,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_match_query_with_all_options(): void
    {
        $query = (new MatchQuery('title', 'search text'))
            ->analyzer('standard')
            ->fuzziness(2)
            ->operator('or')
            ->minimumShouldMatch(2)
            ->boost(1.5)
            ->maxExpansions(50)
            ->prefixLength(1)
            ->fuzzyTranspositions(true)
            ->fuzzyRewrite('constant_score')
            ->lenient(true)
            ->zeroTermsQuery('all')
            ->autoGenerateSynonymsPhraseQuery(false);

        $this->assertSame([
            'match' => ['title' => [
                'query' => 'search text',
                'analyzer' => 'standard',
                'fuzziness' => 2,
                'max_expansions' => 50,
                'prefix_length' => 1,
                'fuzzy_transpositions' => true,
                'fuzzy_rewrite' => 'constant_score',
                'operator' => 'or',
                'minimum_should_match' => 2,
                'boost' => 1.5,
                'lenient' => true,
                'zero_terms_query' => 'all',
                'auto_generate_synonyms_phrase_query' => false,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new MatchQuery('title', 'test');

        $this->assertSame($query, $query->analyzer('standard'));
        $this->assertSame($query, $query->fuzziness('AUTO'));
        $this->assertSame($query, $query->operator('and'));
        $this->assertSame($query, $query->minimumShouldMatch(1));
        $this->assertSame($query, $query->boost(1.0));
        $this->assertSame($query, $query->maxExpansions(50));
        $this->assertSame($query, $query->prefixLength(0));
        $this->assertSame($query, $query->fuzzyTranspositions(true));
        $this->assertSame($query, $query->fuzzyRewrite('constant_score'));
        $this->assertSame($query, $query->lenient(true));
        $this->assertSame($query, $query->zeroTermsQuery('all'));
        $this->assertSame($query, $query->autoGenerateSynonymsPhraseQuery(true));
    }
}
