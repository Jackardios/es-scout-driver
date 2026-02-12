<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\FullText;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\FullText\CombinedFieldsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CombinedFieldsQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_combined_fields_query(): void
    {
        $query = new CombinedFieldsQuery(['title', 'body'], 'search text');

        $this->assertSame([
            'combined_fields' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_combined_fields_query_with_operator(): void
    {
        $query = (new CombinedFieldsQuery(['title', 'body'], 'search text'))
            ->operator('and');

        $this->assertSame([
            'combined_fields' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
                'operator' => 'and',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_combined_fields_query_with_minimum_should_match(): void
    {
        $query = (new CombinedFieldsQuery(['title', 'body'], 'search text'))
            ->minimumShouldMatch('75%');

        $this->assertSame([
            'combined_fields' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
                'minimum_should_match' => '75%',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_combined_fields_query_with_boost(): void
    {
        $query = (new CombinedFieldsQuery(['title', 'body'], 'search text'))
            ->boost(2.0);

        $this->assertSame([
            'combined_fields' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
                'boost' => 2.0,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_combined_fields_query_with_zero_terms_query(): void
    {
        $query = (new CombinedFieldsQuery(['title', 'body'], 'search text'))
            ->zeroTermsQuery('none');

        $this->assertSame([
            'combined_fields' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
                'zero_terms_query' => 'none',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_combined_fields_query_with_auto_generate_synonyms_phrase_query(): void
    {
        $query = (new CombinedFieldsQuery(['title', 'body'], 'search text'))
            ->autoGenerateSynonymsPhraseQuery(false);

        $this->assertSame([
            'combined_fields' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
                'auto_generate_synonyms_phrase_query' => false,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_combined_fields_query_with_all_options(): void
    {
        $query = (new CombinedFieldsQuery(['title^2', 'body'], 'search text'))
            ->operator('and')
            ->minimumShouldMatch('2')
            ->boost(1.5)
            ->zeroTermsQuery('all')
            ->autoGenerateSynonymsPhraseQuery(true);

        $this->assertSame([
            'combined_fields' => [
                'fields' => ['title^2', 'body'],
                'query' => 'search text',
                'operator' => 'and',
                'minimum_should_match' => '2',
                'boost' => 1.5,
                'zero_terms_query' => 'all',
                'auto_generate_synonyms_phrase_query' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_throws_exception_for_empty_fields_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('CombinedFieldsQuery requires at least one field');

        new CombinedFieldsQuery([], 'search text');
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new CombinedFieldsQuery(['title', 'body'], 'search text');

        $this->assertSame($query, $query->operator('and'));
        $this->assertSame($query, $query->minimumShouldMatch('75%'));
        $this->assertSame($query, $query->boost(1.5));
        $this->assertSame($query, $query->zeroTermsQuery('none'));
        $this->assertSame($query, $query->autoGenerateSynonymsPhraseQuery(false));
    }
}
