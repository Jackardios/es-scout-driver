<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\FullText;

use Jackardios\EsScoutDriver\Query\FullText\SimpleQueryStringQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SimpleQueryStringQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_simple_query_string_query(): void
    {
        $query = new SimpleQueryStringQuery('"fried eggs" +(eggplant | potato)');

        $this->assertSame([
            'simple_query_string' => ['query' => '"fried eggs" +(eggplant | potato)'],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_simple_query_string_with_fields_and_operator(): void
    {
        $query = (new SimpleQueryStringQuery('search text'))
            ->fields(['title', 'body'])
            ->defaultOperator('AND')
            ->analyzer('standard');

        $this->assertSame([
            'simple_query_string' => [
                'query' => 'search text',
                'fields' => ['title', 'body'],
                'default_operator' => 'AND',
                'analyzer' => 'standard',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_simple_query_string_with_all_options(): void
    {
        $query = (new SimpleQueryStringQuery('search text'))
            ->fields(['title^2', 'body'])
            ->defaultOperator('OR')
            ->analyzer('custom_analyzer')
            ->lenient(true)
            ->minimumShouldMatch('75%')
            ->analyzeWildcard(true)
            ->autoGenerateSynonymsPhraseQuery(false)
            ->flags('OR|AND|PREFIX');

        $this->assertSame([
            'simple_query_string' => [
                'query' => 'search text',
                'fields' => ['title^2', 'body'],
                'default_operator' => 'OR',
                'analyzer' => 'custom_analyzer',
                'lenient' => true,
                'minimum_should_match' => '75%',
                'analyze_wildcard' => true,
                'auto_generate_synonyms_phrase_query' => false,
                'flags' => 'OR|AND|PREFIX',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_simple_query_string_with_fuzzy_prefix_length(): void
    {
        $query = (new SimpleQueryStringQuery('search text'))
            ->fuzzyPrefixLength(2);

        $this->assertSame([
            'simple_query_string' => [
                'query' => 'search text',
                'fuzzy_prefix_length' => 2,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_simple_query_string_with_fuzzy_max_expansions(): void
    {
        $query = (new SimpleQueryStringQuery('search text'))
            ->fuzzyMaxExpansions(50);

        $this->assertSame([
            'simple_query_string' => [
                'query' => 'search text',
                'fuzzy_max_expansions' => 50,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_simple_query_string_with_fuzzy_transpositions(): void
    {
        $query = (new SimpleQueryStringQuery('search text'))
            ->fuzzyTranspositions(false);

        $this->assertSame([
            'simple_query_string' => [
                'query' => 'search text',
                'fuzzy_transpositions' => false,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_simple_query_string_with_quote_field_suffix(): void
    {
        $query = (new SimpleQueryStringQuery('search text'))
            ->quoteFieldSuffix('.exact');

        $this->assertSame([
            'simple_query_string' => [
                'query' => 'search text',
                'quote_field_suffix' => '.exact',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new SimpleQueryStringQuery('search text');

        $this->assertSame($query, $query->fields(['title', 'body']));
        $this->assertSame($query, $query->defaultOperator('AND'));
        $this->assertSame($query, $query->analyzer('standard'));
        $this->assertSame($query, $query->lenient(true));
        $this->assertSame($query, $query->minimumShouldMatch('75%'));
        $this->assertSame($query, $query->analyzeWildcard(true));
        $this->assertSame($query, $query->autoGenerateSynonymsPhraseQuery(false));
        $this->assertSame($query, $query->flags('ALL'));
        $this->assertSame($query, $query->fuzzyPrefixLength(2));
        $this->assertSame($query, $query->fuzzyMaxExpansions(50));
        $this->assertSame($query, $query->fuzzyTranspositions(false));
        $this->assertSame($query, $query->quoteFieldSuffix('.exact'));
    }
}
