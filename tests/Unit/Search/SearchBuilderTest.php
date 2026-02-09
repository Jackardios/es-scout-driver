<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Jackardios\EsScoutDriver\Enums\SoftDeleteMode;
use Jackardios\EsScoutDriver\Enums\SortOrder;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use Jackardios\EsScoutDriver\Query\Term\ExistsQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests that don't require a real model/engine.
 * We test buildParams logic on a manually constructed BoolQuery.
 */
final class SearchBuilderTest extends TestCase
{
    #[Test]
    public function bool_query_shortcut_must(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('status', 'active'));

        $this->assertTrue($bool->hasClauses());
        $this->assertCount(1, $bool->getMustClauses());
    }

    #[Test]
    public function bool_query_shortcut_filter(): void
    {
        $bool = new BoolQuery();
        $bool->addFilter(new TermQuery('status', 'active'));

        $this->assertCount(1, $bool->getFilterClauses());
    }

    #[Test]
    public function bool_query_shortcut_must_not(): void
    {
        $bool = new BoolQuery();
        $bool->addMustNot(new ExistsQuery('deleted_at'));

        $this->assertCount(1, $bool->getMustNotClauses());
    }

    #[Test]
    public function bool_query_shortcut_should(): void
    {
        $bool = new BoolQuery();
        $bool->addShould(new MatchQuery('title', 'test'));

        $this->assertCount(1, $bool->getShouldClauses());
    }

    #[Test]
    public function bool_query_keyed_clauses_via_builder(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('status', 'active'), key: 'status');
        $bool->addMust(new TermQuery('status', 'inactive'), key: 'status');

        // Second must with same key should be ignored
        $this->assertCount(1, $bool->getMustClauses());
        $this->assertTrue($bool->hasClause('must', 'status'));
    }

    #[Test]
    public function bool_query_accessor_returns_same_instance(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('a', 'b'));

        $same = $bool;
        $same->addFilter(new TermQuery('c', 'd'));

        $this->assertTrue($bool->hasClauses());
        $this->assertCount(1, $bool->getMustClauses());
        $this->assertCount(1, $bool->getFilterClauses());
    }

    #[Test]
    public function bool_query_with_trashed_and_only_trashed(): void
    {
        $bool = new BoolQuery();
        $bool->withTrashed();
        $this->assertSame(SoftDeleteMode::WithTrashed, $bool->getSoftDeleteMode());

        $bool->onlyTrashed();
        $this->assertSame(SoftDeleteMode::OnlyTrashed, $bool->getSoftDeleteMode());

        $bool->excludeTrashed();
        $this->assertSame(SoftDeleteMode::ExcludeTrashed, $bool->getSoftDeleteMode());
    }

    #[Test]
    public function bool_query_builds_combined_query(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new MatchQuery('title', 'test'));
        $bool->addFilter(new TermQuery('status', 'active'));

        $expected = [
            'bool' => [
                'must' => [
                    ['match' => ['title' => ['query' => 'test']]],
                ],
                'filter' => [
                    ['term' => ['status' => ['value' => 'active']]],
                ],
            ],
        ];

        $this->assertSame($expected, $bool->toArray());
    }

    #[Test]
    public function sort_order_enum_works(): void
    {
        $this->assertSame('asc', SortOrder::Asc->value);
        $this->assertSame('desc', SortOrder::Desc->value);
    }

    #[Test]
    public function query_factory_creates_match_all(): void
    {
        $query = Query::matchAll();
        $this->assertInstanceOf(MatchAllQuery::class, $query);
    }

    #[Test]
    public function deep_clone_separates_bool_queries(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('status', 'active'));

        $clone = clone $bool;
        $clone->addFilter(new TermQuery('type', 'book'));

        // Original should not have the filter
        $this->assertCount(0, $bool->getFilterClauses());
        $this->assertCount(1, $clone->getFilterClauses());
    }

    #[Test]
    public function bool_query_minimum_should_match_integer(): void
    {
        $bool = new BoolQuery();
        $bool->addShould(new MatchQuery('title', 'test'));
        $bool->addShould(new MatchQuery('body', 'test'));
        $bool->minimumShouldMatch(1);

        $result = $bool->toArray();

        $this->assertSame(1, $result['bool']['minimum_should_match']);
    }

    #[Test]
    public function bool_query_minimum_should_match_string(): void
    {
        $bool = new BoolQuery();
        $bool->addShould(new MatchQuery('title', 'test'));
        $bool->minimumShouldMatch('75%');

        $result = $bool->toArray();

        $this->assertSame('75%', $result['bool']['minimum_should_match']);
    }

    #[Test]
    public function bool_query_with_boost(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('status', 'active'));
        $bool->boost(1.5);

        $result = $bool->toArray();

        $this->assertSame(1.5, $result['bool']['boost']);
    }

    #[Test]
    public function bool_query_accepts_closure(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(fn() => new TermQuery('status', 'active'));

        $result = $bool->toArray();

        $this->assertArrayHasKey('must', $result['bool']);
        $this->assertCount(1, $result['bool']['must']);
    }

    #[Test]
    public function bool_query_accepts_array(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(['match' => ['title' => 'test']]);

        $result = $bool->toArray();

        $this->assertSame([['match' => ['title' => 'test']]], $result['bool']['must']);
    }

    #[Test]
    public function bool_query_get_clause_returns_query(): void
    {
        $bool = new BoolQuery();
        $termQuery = new TermQuery('status', 'active');
        $bool->addMust($termQuery, key: 'status_filter');

        $retrieved = $bool->getClause('must', 'status_filter');

        $this->assertSame($termQuery, $retrieved);
    }

    #[Test]
    public function bool_query_get_clause_returns_null_for_missing(): void
    {
        $bool = new BoolQuery();

        $this->assertNull($bool->getClause('must', 'nonexistent'));
    }

    #[Test]
    public function bool_query_has_clauses_checks_all_sections(): void
    {
        $bool = new BoolQuery();
        $this->assertFalse($bool->hasClauses());

        $bool->addMust(new TermQuery('status', 'active'));
        $this->assertTrue($bool->hasClauses());
    }

    #[Test]
    public function bool_query_has_clauses_detects_filter_section(): void
    {
        $bool = new BoolQuery();
        $bool->addFilter(new TermQuery('status', 'active'));

        $this->assertTrue($bool->hasClauses());
        $this->assertCount(1, $bool->getFilterClauses());
    }

    #[Test]
    public function bool_query_has_clauses_detects_should_section(): void
    {
        $bool = new BoolQuery();
        $bool->addShould(new MatchQuery('title', 'test'));

        $this->assertTrue($bool->hasClauses());
        $this->assertCount(1, $bool->getShouldClauses());
    }

    #[Test]
    public function bool_query_has_clauses_detects_must_not_section(): void
    {
        $bool = new BoolQuery();
        $bool->addMustNot(new ExistsQuery('deleted_at'));

        $this->assertTrue($bool->hasClauses());
        $this->assertCount(1, $bool->getMustNotClauses());
    }

    #[Test]
    public function bool_query_empty_returns_match_all(): void
    {
        $bool = new BoolQuery();

        $this->assertEquals(['match_all' => new \stdClass()], $bool->toArray());
        $this->assertFalse($bool->hasClauses());
    }

    #[Test]
    public function bool_query_multiple_clauses_same_section(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('status', 'active'));
        $bool->addMust(new TermQuery('type', 'book'));
        $bool->addMust(new MatchQuery('title', 'laravel'));

        $this->assertCount(3, $bool->getMustClauses());
    }

    #[Test]
    public function bool_query_all_sections_combined(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new MatchQuery('title', 'search'));
        $bool->addFilter(new TermQuery('status', 'published'));
        $bool->addShould(new MatchQuery('body', 'keyword'));
        $bool->addMustNot(new ExistsQuery('deleted_at'));
        $bool->minimumShouldMatch(1);
        $bool->boost(2.0);

        $result = $bool->toArray();

        $this->assertArrayHasKey('must', $result['bool']);
        $this->assertArrayHasKey('filter', $result['bool']);
        $this->assertArrayHasKey('should', $result['bool']);
        $this->assertArrayHasKey('must_not', $result['bool']);
        $this->assertSame(1, $result['bool']['minimum_should_match']);
        $this->assertSame(2.0, $result['bool']['boost']);
    }

    // ---- Replacement methods tests ----

    #[Test]
    public function bool_query_set_must_replaces_all_clauses(): void
    {
        $bool = new BoolQuery();
        $bool->setMust(new TermQuery('status', 'active'));
        $bool->setMust(new TermQuery('type', 'book'));

        // Second call should replace, not add
        $this->assertCount(1, $bool->getMustClauses());
    }

    #[Test]
    public function bool_query_set_must_variadic(): void
    {
        $bool = new BoolQuery();
        $bool->setMust(
            new TermQuery('status', 'active'),
            new TermQuery('type', 'book'),
        );

        $this->assertCount(2, $bool->getMustClauses());
    }

    // ---- Tests for addMustMany behavior (must() now adds, not replaces) ----

    #[Test]
    public function bool_query_add_must_many_accumulates(): void
    {
        $bool = new BoolQuery();
        $bool->addMustMany(new TermQuery('status', 'active'));
        $bool->addMustMany(new TermQuery('type', 'book'));

        $this->assertCount(2, $bool->getMustClauses());
    }

    #[Test]
    public function bool_query_add_must_many_variadic(): void
    {
        $bool = new BoolQuery();
        $bool->addMustMany(
            new TermQuery('status', 'active'),
            new TermQuery('type', 'book'),
            new MatchQuery('title', 'test'),
        );

        $this->assertCount(3, $bool->getMustClauses());
    }

    #[Test]
    public function bool_query_add_filter_many_accumulates(): void
    {
        $bool = new BoolQuery();
        $bool->addFilterMany(new TermQuery('status', 'active'));
        $bool->addFilterMany(new TermQuery('type', 'book'));

        $this->assertCount(2, $bool->getFilterClauses());
    }

    #[Test]
    public function bool_query_add_should_many_accumulates(): void
    {
        $bool = new BoolQuery();
        $bool->addShouldMany(new MatchQuery('title', 'test'));
        $bool->addShouldMany(new MatchQuery('body', 'test'));

        $this->assertCount(2, $bool->getShouldClauses());
    }

    #[Test]
    public function bool_query_add_must_not_many_accumulates(): void
    {
        $bool = new BoolQuery();
        $bool->addMustNotMany(new ExistsQuery('deleted_at'));
        $bool->addMustNotMany(new TermQuery('status', 'hidden'));

        $this->assertCount(2, $bool->getMustNotClauses());
    }

    // ---- Clear methods tests ----

    #[Test]
    public function bool_query_clear_must(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('status', 'active'));
        $bool->addMust(new TermQuery('type', 'book'));
        $bool->clearMust();

        $this->assertCount(0, $bool->getMustClauses());
    }

    #[Test]
    public function bool_query_clear_filter(): void
    {
        $bool = new BoolQuery();
        $bool->addFilter(new TermQuery('status', 'active'));
        $bool->clearFilter();

        $this->assertCount(0, $bool->getFilterClauses());
    }

    #[Test]
    public function bool_query_clear_should(): void
    {
        $bool = new BoolQuery();
        $bool->addShould(new MatchQuery('title', 'test'));
        $bool->clearShould();

        $this->assertCount(0, $bool->getShouldClauses());
    }

    #[Test]
    public function bool_query_clear_must_not(): void
    {
        $bool = new BoolQuery();
        $bool->addMustNot(new ExistsQuery('deleted_at'));
        $bool->clearMustNot();

        $this->assertCount(0, $bool->getMustNotClauses());
    }

    #[Test]
    public function bool_query_clear_all_sections(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('status', 'active'));
        $bool->addFilter(new TermQuery('type', 'book'));
        $bool->addShould(new MatchQuery('title', 'test'));
        $bool->addMustNot(new ExistsQuery('deleted_at'));
        $bool->clear();

        $this->assertFalse($bool->hasClauses());
    }

    // ---- Remove keyed clause tests ----

    #[Test]
    public function bool_query_remove_must_by_key(): void
    {
        $bool = new BoolQuery();
        $bool->addMust(new TermQuery('status', 'active'), key: 'status');
        $bool->addMust(new TermQuery('type', 'book'), key: 'type');
        $bool->removeMust('status');

        $this->assertCount(1, $bool->getMustClauses());
        $this->assertFalse($bool->hasClause('must', 'status'));
        $this->assertTrue($bool->hasClause('must', 'type'));
    }

    #[Test]
    public function bool_query_remove_filter_by_key(): void
    {
        $bool = new BoolQuery();
        $bool->addFilter(new TermQuery('status', 'active'), key: 'status');
        $bool->removeFilter('status');

        $this->assertCount(0, $bool->getFilterClauses());
    }

    #[Test]
    public function bool_query_remove_should_by_key(): void
    {
        $bool = new BoolQuery();
        $bool->addShould(new MatchQuery('title', 'test'), key: 'title');
        $bool->removeShould('title');

        $this->assertCount(0, $bool->getShouldClauses());
    }

    #[Test]
    public function bool_query_remove_must_not_by_key(): void
    {
        $bool = new BoolQuery();
        $bool->addMustNot(new ExistsQuery('deleted_at'), key: 'deleted');
        $bool->removeMustNot('deleted');

        $this->assertCount(0, $bool->getMustNotClauses());
    }
}
