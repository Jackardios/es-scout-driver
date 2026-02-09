<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\FullText;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\FullText\MultiMatchQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MultiMatchQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_multi_match_query(): void
    {
        $query = new MultiMatchQuery(['title', 'body'], 'search text');

        $this->assertSame([
            'multi_match' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_multi_match_query_with_common_options(): void
    {
        $query = (new MultiMatchQuery(['title', 'body'], 'search text'))
            ->type('best_fields')
            ->analyzer('standard')
            ->operator('and')
            ->tieBreaker(0.3)
            ->boost(1.5);

        $this->assertSame([
            'multi_match' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
                'type' => 'best_fields',
                'analyzer' => 'standard',
                'operator' => 'and',
                'tie_breaker' => 0.3,
                'boost' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_multi_match_query_with_all_options(): void
    {
        $query = (new MultiMatchQuery(['title^2', 'body'], 'search text'))
            ->type('cross_fields')
            ->analyzer('custom_analyzer')
            ->operator('or')
            ->minimumShouldMatch('75%')
            ->tieBreaker(0.5)
            ->boost(2.0)
            ->fuzziness('AUTO')
            ->prefixLength(1)
            ->maxExpansions(50)
            ->lenient(true)
            ->zeroTermsQuery('none')
            ->autoGenerateSynonymsPhraseQuery(false)
            ->fuzzyRewrite('constant_score')
            ->fuzzyTranspositions(true);

        $this->assertSame([
            'multi_match' => [
                'fields' => ['title^2', 'body'],
                'query' => 'search text',
                'type' => 'cross_fields',
                'analyzer' => 'custom_analyzer',
                'operator' => 'or',
                'minimum_should_match' => '75%',
                'tie_breaker' => 0.5,
                'boost' => 2.0,
                'fuzziness' => 'AUTO',
                'max_expansions' => 50,
                'prefix_length' => 1,
                'fuzzy_transpositions' => true,
                'fuzzy_rewrite' => 'constant_score',
                'lenient' => true,
                'zero_terms_query' => 'none',
                'auto_generate_synonyms_phrase_query' => false,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new MultiMatchQuery(['title', 'body'], 'search text');

        $this->assertSame($query, $query->type('best_fields'));
        $this->assertSame($query, $query->analyzer('standard'));
        $this->assertSame($query, $query->operator('and'));
        $this->assertSame($query, $query->minimumShouldMatch('75%'));
        $this->assertSame($query, $query->tieBreaker(0.3));
        $this->assertSame($query, $query->boost(1.5));
        $this->assertSame($query, $query->fuzziness('AUTO'));
        $this->assertSame($query, $query->prefixLength(1));
        $this->assertSame($query, $query->maxExpansions(50));
        $this->assertSame($query, $query->lenient(true));
        $this->assertSame($query, $query->zeroTermsQuery('none'));
        $this->assertSame($query, $query->autoGenerateSynonymsPhraseQuery(false));
        $this->assertSame($query, $query->fuzzyRewrite('constant_score'));
        $this->assertSame($query, $query->fuzzyTranspositions(true));
    }

    #[Test]
    public function it_throws_exception_for_empty_fields_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('MultiMatchQuery requires at least one field');

        new MultiMatchQuery([], 'search text');
    }
}
