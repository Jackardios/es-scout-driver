<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Compound;

use Jackardios\EsScoutDriver\Enums\SoftDeleteMode;
use Jackardios\EsScoutDriver\Exceptions\DuplicateKeyedClauseException;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class BoolQueryTest extends TestCase
{
    #[Test]
    public function it_builds_empty_bool_query_as_match_all(): void
    {
        $query = new BoolQuery();

        $this->assertEquals(['match_all' => new stdClass()], $query->toArray());
    }

    #[Test]
    public function it_reports_empty_correctly(): void
    {
        $query = new BoolQuery();
        $this->assertTrue($query->isEmpty());
        $this->assertFalse($query->hasClauses());

        $query->addMust(new TermQuery('status', 'active'));
        $this->assertFalse($query->isEmpty());
        $this->assertTrue($query->hasClauses());
    }

    // ---- Set methods (replacement, variadic) ----

    #[Test]
    public function it_builds_bool_query_with_set_must(): void
    {
        $query = (new BoolQuery())
            ->setMust(new TermQuery('status', 'active'));

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['status' => ['value' => 'active']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_replaces_must_clauses_completely(): void
    {
        $query = (new BoolQuery())
            ->setMust(new TermQuery('status', 'active'))
            ->setMust(new TermQuery('type', 'post'));

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_accepts_multiple_must_clauses_via_variadic(): void
    {
        $query = (new BoolQuery())
            ->setMust(
                new TermQuery('status', 'active'),
                new TermQuery('type', 'post'),
            );

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['status' => ['value' => 'active']]],
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_bool_query_with_set_must_not(): void
    {
        $query = (new BoolQuery())
            ->setMustNot(new TermQuery('status', 'deleted'));

        $this->assertSame([
            'bool' => [
                'must_not' => [
                    ['term' => ['status' => ['value' => 'deleted']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_bool_query_with_set_should(): void
    {
        $query = (new BoolQuery())
            ->setShould(
                new TermQuery('priority', 'high'),
                new TermQuery('priority', 'medium'),
            );

        $this->assertSame([
            'bool' => [
                'should' => [
                    ['term' => ['priority' => ['value' => 'high']]],
                    ['term' => ['priority' => ['value' => 'medium']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_bool_query_with_set_filter(): void
    {
        $query = (new BoolQuery())
            ->setFilter(new TermQuery('status', 'published'));

        $this->assertSame([
            'bool' => [
                'filter' => [
                    ['term' => ['status' => ['value' => 'published']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_bool_query_with_array_clause(): void
    {
        $query = (new BoolQuery())
            ->setMust(['match' => ['title' => 'search text']]);

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['match' => ['title' => 'search text']],
                ],
            ],
        ], $query->toArray());
    }

    // ---- Add methods (single clause) ----

    #[Test]
    public function it_adds_must_clause(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'))
            ->addMust(new TermQuery('type', 'post'));

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['status' => ['value' => 'active']]],
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_adds_must_not_clause(): void
    {
        $query = (new BoolQuery())
            ->addMustNot(new TermQuery('status', 'deleted'));

        $this->assertSame([
            'bool' => [
                'must_not' => [
                    ['term' => ['status' => ['value' => 'deleted']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_adds_should_clauses(): void
    {
        $query = (new BoolQuery())
            ->addShould(new TermQuery('priority', 'high'))
            ->addShould(new TermQuery('priority', 'medium'));

        $this->assertSame([
            'bool' => [
                'should' => [
                    ['term' => ['priority' => ['value' => 'high']]],
                    ['term' => ['priority' => ['value' => 'medium']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_adds_filter_clause(): void
    {
        $query = (new BoolQuery())
            ->addFilter(new TermQuery('status', 'published'));

        $this->assertSame([
            'bool' => [
                'filter' => [
                    ['term' => ['status' => ['value' => 'published']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_adds_must_clause_with_closure(): void
    {
        $query = (new BoolQuery())
            ->addMust(fn() => new TermQuery('status', 'active'));

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['status' => ['value' => 'active']]],
                ],
            ],
        ], $query->toArray());
    }

    // ---- Add many methods ----

    #[Test]
    public function it_adds_many_must_clauses(): void
    {
        $query = (new BoolQuery())
            ->addMustMany(
                new TermQuery('status', 'active'),
                new TermQuery('type', 'post'),
            );

        $this->assertCount(2, $query->getMustClauses());
    }

    #[Test]
    public function it_adds_many_must_not_clauses(): void
    {
        $query = (new BoolQuery())
            ->addMustNotMany(
                new TermQuery('status', 'deleted'),
                new TermQuery('archived', true),
            );

        $this->assertCount(2, $query->getMustNotClauses());
    }

    #[Test]
    public function it_adds_many_should_clauses(): void
    {
        $query = (new BoolQuery())
            ->addShouldMany(
                new TermQuery('priority', 'high'),
                new TermQuery('priority', 'medium'),
            );

        $this->assertCount(2, $query->getShouldClauses());
    }

    #[Test]
    public function it_adds_many_filter_clauses(): void
    {
        $query = (new BoolQuery())
            ->addFilterMany(
                new TermQuery('status', 'active'),
                new TermQuery('type', 'post'),
            );

        $this->assertCount(2, $query->getFilterClauses());
    }

    // ---- Keyed clauses ----

    #[Test]
    public function it_supports_keyed_clauses_with_has_clause(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'), key: 'status_check');

        $this->assertTrue($query->hasClause('must', 'status_check'));
        $this->assertFalse($query->hasClause('must', 'nonexistent'));
        $this->assertFalse($query->hasClause('filter', 'status_check'));
    }

    #[Test]
    public function it_supports_keyed_clauses_with_get_clause(): void
    {
        $termQuery = new TermQuery('status', 'active');
        $query = (new BoolQuery())
            ->addMust($termQuery, key: 'status_check');

        $this->assertSame($termQuery, $query->getClause('must', 'status_check'));
        $this->assertNull($query->getClause('must', 'nonexistent'));
        $this->assertNull($query->getClause('filter', 'status_check'));
    }

    #[Test]
    public function it_ignores_duplicate_keyed_clauses_by_default(): void
    {
        $firstQuery = new TermQuery('status', 'active');
        $secondQuery = new TermQuery('status', 'pending');

        $query = (new BoolQuery())
            ->addMust($firstQuery, key: 'status_check')
            ->addMust($secondQuery, key: 'status_check');

        $this->assertSame($firstQuery, $query->getClause('must', 'status_check'));
        $this->assertCount(1, $query->getMustClauses());
    }

    #[Test]
    public function it_throws_on_duplicate_keyed_clause_when_not_ignored(): void
    {
        $this->expectException(DuplicateKeyedClauseException::class);

        (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'), key: 'status_check')
            ->addMust(new TermQuery('status', 'pending'), key: 'status_check', ignoreIfKeyExists: false);
    }

    // ---- Remove clause by key ----

    #[Test]
    public function it_removes_must_clause_by_key(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'), key: 'status')
            ->addMust(new TermQuery('type', 'post'), key: 'type')
            ->removeMust('status');

        $this->assertCount(1, $query->getMustClauses());
        $this->assertFalse($query->hasClause('must', 'status'));
        $this->assertTrue($query->hasClause('must', 'type'));
    }

    #[Test]
    public function it_removes_must_not_clause_by_key(): void
    {
        $query = (new BoolQuery())
            ->addMustNot(new TermQuery('status', 'deleted'), key: 'deleted')
            ->removeMustNot('deleted');

        $this->assertCount(0, $query->getMustNotClauses());
    }

    #[Test]
    public function it_removes_should_clause_by_key(): void
    {
        $query = (new BoolQuery())
            ->addShould(new TermQuery('priority', 'high'), key: 'high')
            ->removeShould('high');

        $this->assertCount(0, $query->getShouldClauses());
    }

    #[Test]
    public function it_removes_filter_clause_by_key(): void
    {
        $query = (new BoolQuery())
            ->addFilter(new TermQuery('status', 'active'), key: 'active')
            ->removeFilter('active');

        $this->assertCount(0, $query->getFilterClauses());
    }

    // ---- Introspection ----

    #[Test]
    public function it_returns_must_clauses(): void
    {
        $termQuery = new TermQuery('status', 'active');
        $query = (new BoolQuery())->addMust($termQuery);

        $clauses = $query->getMustClauses();
        $this->assertCount(1, $clauses);
        $this->assertSame($termQuery, $clauses[0]);
    }

    #[Test]
    public function it_returns_must_not_clauses(): void
    {
        $termQuery = new TermQuery('status', 'deleted');
        $query = (new BoolQuery())->addMustNot($termQuery);

        $clauses = $query->getMustNotClauses();
        $this->assertCount(1, $clauses);
        $this->assertSame($termQuery, $clauses[0]);
    }

    #[Test]
    public function it_returns_should_clauses(): void
    {
        $q1 = new TermQuery('priority', 'high');
        $q2 = new TermQuery('priority', 'medium');
        $query = (new BoolQuery())->addShould($q1)->addShould($q2);

        $clauses = $query->getShouldClauses();
        $this->assertCount(2, $clauses);
        $this->assertSame($q1, $clauses[0]);
        $this->assertSame($q2, $clauses[1]);
    }

    #[Test]
    public function it_returns_filter_clauses(): void
    {
        $termQuery = new TermQuery('status', 'active');
        $query = (new BoolQuery())->addFilter($termQuery);

        $clauses = $query->getFilterClauses();
        $this->assertCount(1, $clauses);
        $this->assertSame($termQuery, $clauses[0]);
    }

    // ---- Options ----

    #[Test]
    public function it_sets_minimum_should_match(): void
    {
        $query = (new BoolQuery())
            ->addShould(new TermQuery('a', '1'))
            ->addShould(new TermQuery('b', '2'))
            ->minimumShouldMatch(1);

        $result = $query->toArray();
        $this->assertSame(1, $result['bool']['minimum_should_match']);
    }

    #[Test]
    public function it_sets_minimum_should_match_as_string(): void
    {
        $query = (new BoolQuery())
            ->addShould(new TermQuery('a', '1'))
            ->minimumShouldMatch('75%');

        $result = $query->toArray();
        $this->assertSame('75%', $result['bool']['minimum_should_match']);
    }

    #[Test]
    public function it_sets_boost(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'))
            ->boost(1.5);

        $result = $query->toArray();
        $this->assertSame(1.5, $result['bool']['boost']);
    }

    // ---- Soft delete mode ----

    #[Test]
    public function it_defaults_to_exclude_trashed(): void
    {
        $query = new BoolQuery();
        $this->assertSame(SoftDeleteMode::ExcludeTrashed, $query->getSoftDeleteMode());
    }

    #[Test]
    public function it_sets_soft_delete_mode_via_enum(): void
    {
        $query = (new BoolQuery())->softDelete(SoftDeleteMode::WithTrashed);
        $this->assertSame(SoftDeleteMode::WithTrashed, $query->getSoftDeleteMode());
    }

    #[Test]
    public function it_sets_with_trashed(): void
    {
        $query = (new BoolQuery())->withTrashed();
        $this->assertSame(SoftDeleteMode::WithTrashed, $query->getSoftDeleteMode());
    }

    #[Test]
    public function it_sets_only_trashed(): void
    {
        $query = (new BoolQuery())->onlyTrashed();
        $this->assertSame(SoftDeleteMode::OnlyTrashed, $query->getSoftDeleteMode());
    }

    #[Test]
    public function it_sets_exclude_trashed(): void
    {
        $query = (new BoolQuery())->withTrashed()->excludeTrashed();
        $this->assertSame(SoftDeleteMode::ExcludeTrashed, $query->getSoftDeleteMode());
    }

    // ---- Clear methods ----

    #[Test]
    public function it_clears_must_clauses(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'))
            ->addMust(new TermQuery('type', 'post'))
            ->clearMust();

        $this->assertSame([], $query->getMustClauses());
        $this->assertEquals(['match_all' => new stdClass()], $query->toArray());
    }

    #[Test]
    public function it_clears_must_not_clauses(): void
    {
        $query = (new BoolQuery())
            ->addMustNot(new TermQuery('status', 'deleted'))
            ->clearMustNot();

        $this->assertSame([], $query->getMustNotClauses());
    }

    #[Test]
    public function it_clears_should_clauses(): void
    {
        $query = (new BoolQuery())
            ->addShould(new TermQuery('priority', 'high'))
            ->addShould(new TermQuery('priority', 'medium'))
            ->clearShould();

        $this->assertSame([], $query->getShouldClauses());
    }

    #[Test]
    public function it_clears_filter_clauses(): void
    {
        $query = (new BoolQuery())
            ->addFilter(new TermQuery('status', 'published'))
            ->clearFilter();

        $this->assertSame([], $query->getFilterClauses());
    }

    #[Test]
    public function it_clears_all_sections(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('a', '1'))
            ->addMustNot(new TermQuery('b', '2'))
            ->addShould(new TermQuery('c', '3'))
            ->addFilter(new TermQuery('d', '4'))
            ->clear();

        $this->assertTrue($query->isEmpty());
    }

    #[Test]
    public function it_clears_only_specified_section(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'))
            ->addFilter(new TermQuery('type', 'post'))
            ->clearMust();

        $this->assertSame([], $query->getMustClauses());
        $this->assertCount(1, $query->getFilterClauses());
    }

    // ---- Full query composition ----

    #[Test]
    public function it_composes_full_bool_query_with_add_methods(): void
    {
        $query = (new BoolQuery())
            ->addMust(new MatchQuery('title', 'search'))
            ->addFilter(new TermQuery('status', 'published'))
            ->addMustNot(new TermQuery('deleted', true))
            ->addShould(new TermQuery('featured', true))
            ->minimumShouldMatch(1)
            ->boost(1.2);

        $result = $query->toArray();

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['match' => ['title' => ['query' => 'search']]],
                ],
                'must_not' => [
                    ['term' => ['deleted' => ['value' => true]]],
                ],
                'should' => [
                    ['term' => ['featured' => ['value' => true]]],
                ],
                'filter' => [
                    ['term' => ['status' => ['value' => 'published']]],
                ],
                'minimum_should_match' => 1,
                'boost' => 1.2,
            ],
        ], $result);
    }

    #[Test]
    public function it_composes_full_bool_query_with_set_methods(): void
    {
        $query = (new BoolQuery())
            ->setMust(new MatchQuery('title', 'search'))
            ->setFilter(new TermQuery('status', 'published'))
            ->setMustNot(new TermQuery('deleted', true))
            ->setShould(new TermQuery('featured', true))
            ->minimumShouldMatch(1)
            ->boost(1.2);

        $result = $query->toArray();

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['match' => ['title' => ['query' => 'search']]],
                ],
                'must_not' => [
                    ['term' => ['deleted' => ['value' => true]]],
                ],
                'should' => [
                    ['term' => ['featured' => ['value' => true]]],
                ],
                'filter' => [
                    ['term' => ['status' => ['value' => 'published']]],
                ],
                'minimum_should_match' => 1,
                'boost' => 1.2,
            ],
        ], $result);
    }

    // ---- Fluent interface ----

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new BoolQuery();

        // Set methods
        $this->assertSame($query, $query->setMust(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->setMustNot(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->setShould(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->setFilter(new TermQuery('a', 'b')));

        // Add methods
        $this->assertSame($query, $query->addMust(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->addMustNot(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->addShould(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->addFilter(new TermQuery('a', 'b')));

        // Add many methods
        $this->assertSame($query, $query->addMustMany(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->addMustNotMany(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->addShouldMany(new TermQuery('a', 'b')));
        $this->assertSame($query, $query->addFilterMany(new TermQuery('a', 'b')));

        // Clear methods
        $this->assertSame($query, $query->clearMust());
        $this->assertSame($query, $query->clearMustNot());
        $this->assertSame($query, $query->clearShould());
        $this->assertSame($query, $query->clearFilter());
        $this->assertSame($query, $query->clear());

        // Soft delete
        $this->assertSame($query, $query->withTrashed());
        $this->assertSame($query, $query->onlyTrashed());
        $this->assertSame($query, $query->excludeTrashed());
        $this->assertSame($query, $query->softDelete(SoftDeleteMode::WithTrashed));

        // Options
        $this->assertSame($query, $query->minimumShouldMatch(1));
        $this->assertSame($query, $query->boost(1.0));
    }

    #[Test]
    public function keyed_clauses_are_stripped_from_to_array_output(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'), key: 'status_check')
            ->addMust(new TermQuery('type', 'post'), key: 'type_check');

        $result = $query->toArray();

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['status' => ['value' => 'active']]],
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $result);
    }

    #[Test]
    public function add_methods_work_after_set_methods(): void
    {
        $query = (new BoolQuery())
            ->setMust(new TermQuery('status', 'active'))
            ->addMust(new TermQuery('type', 'post'));

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['status' => ['value' => 'active']]],
                    ['term' => ['type' => ['value' => 'post']]],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function set_methods_clear_added_clauses(): void
    {
        $query = (new BoolQuery())
            ->addMust(new TermQuery('status', 'active'))
            ->addMust(new TermQuery('type', 'post'))
            ->setMust(new TermQuery('only', 'this'));

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['only' => ['value' => 'this']]],
                ],
            ],
        ], $query->toArray());
    }

    // ---- Cloning ----

    #[Test]
    public function it_deep_clones_clauses(): void
    {
        $termQuery = new TermQuery('status', 'active');
        $original = (new BoolQuery())->addMust($termQuery);

        $cloned = clone $original;

        // Verify the clone has the same structure
        $this->assertEquals($original->toArray(), $cloned->toArray());

        // Verify clauses are different instances
        $originalClauses = $original->getMustClauses();
        $clonedClauses = $cloned->getMustClauses();

        $this->assertNotSame($originalClauses[0], $clonedClauses[0]);
    }
}
