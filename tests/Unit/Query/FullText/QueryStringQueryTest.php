<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\FullText;

use Jackardios\EsScoutDriver\Query\FullText\QueryStringQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryStringQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_query_string_query(): void
    {
        $query = new QueryStringQuery('title:search');

        $this->assertSame([
            'query_string' => ['query' => 'title:search'],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_default_field(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->defaultField('title');

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'default_field' => 'title',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_fields(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->fields(['title', 'body']);

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'fields' => ['title', 'body'],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_all_options(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->defaultField('title')
            ->fields(['title^2', 'body'])
            ->analyzer('standard')
            ->defaultOperator('AND')
            ->boost(2.0)
            ->minimumShouldMatch('75%')
            ->lenient(true)
            ->analyzeWildcard(true)
            ->allowLeadingWildcard(false)
            ->autoGenerateSynonymsPhraseQuery(true)
            ->tieBreaker(0.3);

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'default_field' => 'title',
                'fields' => ['title^2', 'body'],
                'analyzer' => 'standard',
                'default_operator' => 'AND',
                'boost' => 2.0,
                'minimum_should_match' => '75%',
                'lenient' => true,
                'analyze_wildcard' => true,
                'allow_leading_wildcard' => false,
                'auto_generate_synonyms_phrase_query' => true,
                'tie_breaker' => 0.3,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_fuzziness(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->fuzziness('AUTO')
            ->maxExpansions(50)
            ->prefixLength(2)
            ->fuzzyTranspositions(true)
            ->fuzzyRewrite('constant_score');

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'fuzziness' => 'AUTO',
                'max_expansions' => 50,
                'prefix_length' => 2,
                'fuzzy_transpositions' => true,
                'fuzzy_rewrite' => 'constant_score',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_phrase_slop(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->phraseSlop(2);

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'phrase_slop' => 2,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_quote_field_suffix(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->quoteFieldSuffix('.exact');

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'quote_field_suffix' => '.exact',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_quote_analyzer(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->quoteAnalyzer('standard');

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'quote_analyzer' => 'standard',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_enable_position_increments(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->enablePositionIncrements(false);

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'enable_position_increments' => false,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_escape(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->escape(true);

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'escape' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_query_string_query_with_rewrite(): void
    {
        $query = (new QueryStringQuery('search text'))
            ->rewrite('constant_score');

        $this->assertSame([
            'query_string' => [
                'query' => 'search text',
                'rewrite' => 'constant_score',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new QueryStringQuery('search text');

        $this->assertSame($query, $query->defaultField('title'));
        $this->assertSame($query, $query->fields(['title', 'body']));
        $this->assertSame($query, $query->analyzer('standard'));
        $this->assertSame($query, $query->defaultOperator('AND'));
        $this->assertSame($query, $query->boost(2.0));
        $this->assertSame($query, $query->minimumShouldMatch('75%'));
        $this->assertSame($query, $query->lenient(true));
        $this->assertSame($query, $query->analyzeWildcard(true));
        $this->assertSame($query, $query->allowLeadingWildcard(false));
        $this->assertSame($query, $query->autoGenerateSynonymsPhraseQuery(true));
        $this->assertSame($query, $query->tieBreaker(0.3));
        $this->assertSame($query, $query->fuzziness('AUTO'));
        $this->assertSame($query, $query->maxExpansions(50));
        $this->assertSame($query, $query->prefixLength(2));
        $this->assertSame($query, $query->fuzzyTranspositions(true));
        $this->assertSame($query, $query->fuzzyRewrite('constant_score'));
        $this->assertSame($query, $query->phraseSlop(2));
        $this->assertSame($query, $query->quoteFieldSuffix('.exact'));
        $this->assertSame($query, $query->quoteAnalyzer('standard'));
        $this->assertSame($query, $query->enablePositionIncrements(false));
        $this->assertSame($query, $query->escape(true));
        $this->assertSame($query, $query->rewrite('constant_score'));
    }
}
