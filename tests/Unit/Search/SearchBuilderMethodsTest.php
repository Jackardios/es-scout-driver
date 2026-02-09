<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Closure;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Enums\SortOrder;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\FieldSort;
use Jackardios\EsScoutDriver\Sort\SortInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class SearchBuilderMethodsTest extends TestCase
{
    private function createBuilder(): SearchBuilder
    {
        $reflection = new ReflectionClass(SearchBuilder::class);
        $builder = $reflection->newInstanceWithoutConstructor();

        $this->setPrivateProperty($builder, 'indexNames', ['TestModel' => 'test_index']);
        $this->setPrivateProperty($builder, 'highlight', []);
        $this->setPrivateProperty($builder, 'sort', []);
        $this->setPrivateProperty($builder, 'rescore', []);
        $this->setPrivateProperty($builder, 'suggest', []);
        $this->setPrivateProperty($builder, 'collapse', []);
        $this->setPrivateProperty($builder, 'aggregations', []);
        $this->setPrivateProperty($builder, 'indicesBoost', []);
        $this->setPrivateProperty($builder, 'queryModifiers', []);
        $this->setPrivateProperty($builder, 'modelModifiers', []);
        $this->setPrivateProperty($builder, 'relations', []);

        return $builder;
    }

    private function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }

    private function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);
        return $reflection->getValue($object);
    }

    // ---- Query methods ----

    #[Test]
    public function query_with_array(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->query(['match_all' => new \stdClass()]);

        $this->assertSame($builder, $result);
        $this->assertEquals(['match_all' => new \stdClass()], $builder->getQuery());
    }

    #[Test]
    public function query_with_query_interface(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->query(new MatchAllQuery());

        $this->assertSame($builder, $result);
        $this->assertEquals(['match_all' => new \stdClass()], $builder->getQuery());
    }

    #[Test]
    public function query_with_closure(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->query(fn() => new MatchAllQuery());

        $this->assertSame($builder, $result);
        $this->assertEquals(['match_all' => new \stdClass()], $builder->getQuery());
    }

    #[Test]
    public function clear_query(): void
    {
        $builder = $this->createBuilder();
        $builder->query(['match_all' => new \stdClass()]);
        $result = $builder->clearQuery();

        $this->assertSame($builder, $result);
        $this->assertNull($builder->getQuery());
    }

    #[Test]
    public function get_query_returns_null_by_default(): void
    {
        $builder = $this->createBuilder();
        $this->assertNull($builder->getQuery());
    }

    // ---- Highlight methods ----

    #[Test]
    public function highlight_with_field(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->highlight('title');

        $this->assertSame($builder, $result);
        $highlight = $builder->getHighlight();
        $this->assertArrayHasKey('fields', $highlight);
        $this->assertArrayHasKey('title', $highlight['fields']);
        $this->assertEquals(new \stdClass(), $highlight['fields']['title']);
    }

    #[Test]
    public function highlight_with_parameters(): void
    {
        $builder = $this->createBuilder();
        $builder->highlight(
            'title',
            ['type' => 'fvh'],
            fragmentSize: 100,
            numberOfFragments: 3,
            preTags: ['<em>'],
            postTags: ['</em>']
        );

        $highlight = $builder->getHighlight();
        $this->assertSame([
            'type' => 'fvh',
            'fragment_size' => 100,
            'number_of_fragments' => 3,
            'pre_tags' => ['<em>'],
            'post_tags' => ['</em>'],
        ], $highlight['fields']['title']);
    }

    #[Test]
    public function highlight_raw(): void
    {
        $builder = $this->createBuilder();
        $raw = ['fields' => ['title' => ['type' => 'unified']]];
        $result = $builder->highlightRaw($raw);

        $this->assertSame($builder, $result);
        $this->assertSame($raw, $builder->getHighlight());
    }

    #[Test]
    public function highlight_global(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->highlightGlobal(
            fragmentSize: 150,
            numberOfFragments: 5,
            preTags: ['<b>'],
            postTags: ['</b>'],
            type: 'unified',
            boundaryScanner: 'sentence',
            encoder: 'html'
        );

        $this->assertSame($builder, $result);
        $highlight = $builder->getHighlight();
        $this->assertSame(150, $highlight['fragment_size']);
        $this->assertSame(5, $highlight['number_of_fragments']);
        $this->assertSame(['<b>'], $highlight['pre_tags']);
        $this->assertSame(['</b>'], $highlight['post_tags']);
        $this->assertSame('unified', $highlight['type']);
        $this->assertSame('sentence', $highlight['boundary_scanner']);
        $this->assertSame('html', $highlight['encoder']);
    }

    #[Test]
    public function clear_highlight(): void
    {
        $builder = $this->createBuilder();
        $builder->highlight('title');
        $result = $builder->clearHighlight();

        $this->assertSame($builder, $result);
        $this->assertSame([], $builder->getHighlight());
    }

    // ---- Sort methods ----

    #[Test]
    public function sort_simple(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->sort('created_at', 'desc');

        $this->assertSame($builder, $result);
        $this->assertSame([['created_at' => 'desc']], $builder->getSort());
    }

    #[Test]
    public function sort_with_options(): void
    {
        $builder = $this->createBuilder();
        $builder->sort('price', 'asc', missing: '_last', mode: 'avg', unmappedType: 'long');

        $this->assertSame([[
            'price' => [
                'order' => 'asc',
                'missing' => '_last',
                'mode' => 'avg',
                'unmapped_type' => 'long',
            ],
        ]], $builder->getSort());
    }

    #[Test]
    public function sort_with_sort_interface(): void
    {
        $builder = $this->createBuilder();
        $fieldSort = (new FieldSort('title'))->asc();
        $result = $builder->sort($fieldSort);

        $this->assertSame($builder, $result);
        $this->assertSame([['title' => 'asc']], $builder->getSort());
    }

    #[Test]
    public function sort_with_sort_order_enum(): void
    {
        $builder = $this->createBuilder();
        $builder->sort('name', SortOrder::Desc);

        $this->assertSame([['name' => 'desc']], $builder->getSort());
    }

    #[Test]
    public function sort_raw(): void
    {
        $builder = $this->createBuilder();
        $raw = [['_score' => 'desc'], ['date' => 'asc']];
        $result = $builder->sortRaw($raw);

        $this->assertSame($builder, $result);
        $this->assertSame($raw, $builder->getSort());
    }

    #[Test]
    public function clear_sort(): void
    {
        $builder = $this->createBuilder();
        $builder->sort('created_at');
        $result = $builder->clearSort();

        $this->assertSame($builder, $result);
        $this->assertSame([], $builder->getSort());
    }

    // ---- Source methods ----

    #[Test]
    public function source_includes_only(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->source(['title', 'description']);

        $this->assertSame($builder, $result);
        $this->assertSame(['title', 'description'], $builder->getSource());
    }

    #[Test]
    public function source_includes_and_excludes(): void
    {
        $builder = $this->createBuilder();
        $builder->source(['title', 'description'], ['metadata.*']);

        $this->assertSame([
            'includes' => ['title', 'description'],
            'excludes' => ['metadata.*'],
        ], $builder->getSource());
    }

    #[Test]
    public function source_raw_boolean(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->sourceRaw(false);

        $this->assertSame($builder, $result);
        $this->assertFalse($builder->getSource());
    }

    #[Test]
    public function source_raw_string(): void
    {
        $builder = $this->createBuilder();
        $builder->sourceRaw('title');

        $this->assertSame('title', $builder->getSource());
    }

    #[Test]
    public function clear_source(): void
    {
        $builder = $this->createBuilder();
        $builder->source(['title']);
        $result = $builder->clearSource();

        $this->assertSame($builder, $result);
        $this->assertNull($builder->getSource());
    }

    // ---- Collapse methods ----

    #[Test]
    public function collapse(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->collapse('category_id');

        $this->assertSame($builder, $result);
        $this->assertSame(['field' => 'category_id'], $builder->getCollapse());
    }

    #[Test]
    public function collapse_raw(): void
    {
        $builder = $this->createBuilder();
        $raw = [
            'field' => 'category_id',
            'inner_hits' => ['name' => 'top_hits', 'size' => 3],
        ];
        $result = $builder->collapseRaw($raw);

        $this->assertSame($builder, $result);
        $this->assertSame($raw, $builder->getCollapse());
    }

    #[Test]
    public function clear_collapse(): void
    {
        $builder = $this->createBuilder();
        $builder->collapse('category_id');
        $result = $builder->clearCollapse();

        $this->assertSame($builder, $result);
        $this->assertSame([], $builder->getCollapse());
    }

    // ---- Aggregation methods ----

    #[Test]
    public function aggregate_with_array(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->aggregate('avg_price', ['avg' => ['field' => 'price']]);

        $this->assertSame($builder, $result);
        $this->assertSame(['avg_price' => ['avg' => ['field' => 'price']]], $builder->getAggregations());
    }

    #[Test]
    public function aggregate_with_aggregation_interface(): void
    {
        $builder = $this->createBuilder();
        $agg = $this->createMock(AggregationInterface::class);
        $agg->method('toArray')->willReturn(['terms' => ['field' => 'status']]);

        $result = $builder->aggregate('by_status', $agg);

        $this->assertSame($builder, $result);
        $this->assertSame(['by_status' => ['terms' => ['field' => 'status']]], $builder->getAggregations());
    }

    #[Test]
    public function aggregate_raw(): void
    {
        $builder = $this->createBuilder();
        $raw = [
            'categories' => ['terms' => ['field' => 'category']],
            'avg_price' => ['avg' => ['field' => 'price']],
        ];
        $result = $builder->aggregateRaw($raw);

        $this->assertSame($builder, $result);
        $this->assertSame($raw, $builder->getAggregations());
    }

    #[Test]
    public function clear_aggregations(): void
    {
        $builder = $this->createBuilder();
        $builder->aggregate('test', ['avg' => ['field' => 'price']]);
        $result = $builder->clearAggregations();

        $this->assertSame($builder, $result);
        $this->assertSame([], $builder->getAggregations());
    }

    // ---- Suggest methods ----

    #[Test]
    public function suggest(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->suggest('title-suggest', [
            'text' => 'test',
            'term' => ['field' => 'title'],
        ]);

        $this->assertSame($builder, $result);
        $this->assertSame([
            'title-suggest' => [
                'text' => 'test',
                'term' => ['field' => 'title'],
            ],
        ], $builder->getSuggest());
    }

    #[Test]
    public function suggest_raw(): void
    {
        $builder = $this->createBuilder();
        $raw = [
            'song-suggest' => [
                'prefix' => 'nir',
                'completion' => ['field' => 'suggest'],
            ],
        ];
        $result = $builder->suggestRaw($raw);

        $this->assertSame($builder, $result);
        $this->assertSame($raw, $builder->getSuggest());
    }

    #[Test]
    public function clear_suggest(): void
    {
        $builder = $this->createBuilder();
        $builder->suggest('test', ['text' => 'x', 'term' => ['field' => 'f']]);
        $result = $builder->clearSuggest();

        $this->assertSame($builder, $result);
        $this->assertSame([], $builder->getSuggest());
    }

    // ---- Rescore methods ----

    #[Test]
    public function rescore_basic(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->rescore(new MatchQuery('title', 'boost me'));

        $this->assertSame($builder, $result);
        $rescore = $builder->getRescore();
        $this->assertArrayHasKey('query', $rescore);
        $this->assertSame(['query' => 'boost me'], $rescore['query']['rescore_query']['match']['title']);
    }

    #[Test]
    public function rescore_with_all_options(): void
    {
        $builder = $this->createBuilder();
        $builder->rescore(
            ['match' => ['title' => 'test']],
            windowSize: 100,
            queryWeight: 0.7,
            rescoreQueryWeight: 1.2
        );

        $rescore = $builder->getRescore();
        $this->assertSame(100, $rescore['window_size']);
        $this->assertSame(0.7, $rescore['query']['query_weight']);
        $this->assertSame(1.2, $rescore['query']['rescore_query_weight']);
    }

    #[Test]
    public function rescore_raw(): void
    {
        $builder = $this->createBuilder();
        $raw = [
            'window_size' => 50,
            'query' => [
                'rescore_query' => ['match_phrase' => ['message' => 'the quick brown']],
                'query_weight' => 0.5,
                'rescore_query_weight' => 2.0,
            ],
        ];
        $result = $builder->rescoreRaw($raw);

        $this->assertSame($builder, $result);
        $this->assertSame($raw, $builder->getRescore());
    }

    #[Test]
    public function clear_rescore(): void
    {
        $builder = $this->createBuilder();
        $builder->rescore(['match_all' => new \stdClass()]);
        $result = $builder->clearRescore();

        $this->assertSame($builder, $result);
        $this->assertSame([], $builder->getRescore());
    }

    // ---- KNN methods ----

    #[Test]
    public function knn_basic(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->knn('embedding', [0.1, 0.2, 0.3], k: 10);

        $this->assertSame($builder, $result);
        $knn = $builder->getKnn();
        $this->assertSame('embedding', $knn['field']);
        $this->assertSame([0.1, 0.2, 0.3], $knn['query_vector']);
        $this->assertSame(10, $knn['k']);
        $this->assertSame(100, $knn['num_candidates']);
    }

    #[Test]
    public function knn_with_all_options(): void
    {
        $builder = $this->createBuilder();
        $builder->knn(
            'embedding',
            [0.1, 0.2, 0.3],
            k: 5,
            numCandidates: 50,
            similarity: 0.8,
            filter: new TermQuery('status', 'active')
        );

        $knn = $builder->getKnn();
        $this->assertSame(5, $knn['k']);
        $this->assertSame(50, $knn['num_candidates']);
        $this->assertSame(0.8, $knn['similarity']);
        $this->assertArrayHasKey('filter', $knn);
        $this->assertSame(['term' => ['status' => ['value' => 'active']]], $knn['filter']);
    }

    #[Test]
    public function knn_with_array_filter(): void
    {
        $builder = $this->createBuilder();
        $builder->knn(
            'embedding',
            [0.5],
            k: 10,
            filter: ['term' => ['status' => 'published']]
        );

        $knn = $builder->getKnn();
        $this->assertSame(['term' => ['status' => 'published']], $knn['filter']);
    }

    #[Test]
    public function knn_raw(): void
    {
        $builder = $this->createBuilder();
        $raw = [
            'field' => 'vector',
            'query_vector' => [1, 2, 3],
            'k' => 20,
            'num_candidates' => 200,
        ];
        $result = $builder->knnRaw($raw);

        $this->assertSame($builder, $result);
        $this->assertSame($raw, $builder->getKnn());
    }

    #[Test]
    public function clear_knn(): void
    {
        $builder = $this->createBuilder();
        $builder->knn('embedding', [0.1], k: 5);
        $result = $builder->clearKnn();

        $this->assertSame($builder, $result);
        $this->assertNull($builder->getKnn());
    }

    // ---- PostFilter methods ----

    #[Test]
    public function post_filter_with_array(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->postFilter(['term' => ['status' => 'published']]);

        $this->assertSame($builder, $result);
        $this->assertSame(['term' => ['status' => 'published']], $builder->getPostFilter());
    }

    #[Test]
    public function post_filter_with_query_interface(): void
    {
        $builder = $this->createBuilder();
        $builder->postFilter(new TermQuery('category', 'books'));

        $this->assertSame(['term' => ['category' => ['value' => 'books']]], $builder->getPostFilter());
    }

    #[Test]
    public function clear_post_filter(): void
    {
        $builder = $this->createBuilder();
        $builder->postFilter(['term' => ['x' => 'y']]);
        $result = $builder->clearPostFilter();

        $this->assertSame($builder, $result);
        $this->assertNull($builder->getPostFilter());
    }

    // ---- Pagination methods ----

    #[Test]
    public function from(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->from(10);

        $this->assertSame($builder, $result);
        $this->assertSame(10, $builder->getFrom());
    }

    #[Test]
    public function size_method(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->size(25);

        $this->assertSame($builder, $result);
        $this->assertSame(25, $builder->getSize());
    }

    // ---- Track & Score methods ----

    #[Test]
    public function track_total_hits_bool(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->trackTotalHits(true);

        $this->assertSame($builder, $result);
        $this->assertTrue($builder->getTrackTotalHits());
    }

    #[Test]
    public function track_total_hits_int(): void
    {
        $builder = $this->createBuilder();
        $builder->trackTotalHits(1000);

        $this->assertSame(1000, $builder->getTrackTotalHits());
    }

    #[Test]
    public function track_scores(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->trackScores(true);

        $this->assertSame($builder, $result);
        $this->assertTrue($builder->getTrackScores());
    }

    #[Test]
    public function min_score(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->minScore(0.5);

        $this->assertSame($builder, $result);
        $this->assertSame(0.5, $builder->getMinScore());
    }

    // ---- Search config methods ----

    #[Test]
    public function search_type(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->searchType('dfs_query_then_fetch');

        $this->assertSame($builder, $result);
        $this->assertSame('dfs_query_then_fetch', $builder->getSearchType());
    }

    #[Test]
    public function preference(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->preference('_local');

        $this->assertSame($builder, $result);
        $this->assertSame('_local', $builder->getPreference());
    }

    #[Test]
    public function point_in_time_id_only(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->pointInTime('pit-id-123');

        $this->assertSame($builder, $result);
        $this->assertSame(['id' => 'pit-id-123'], $builder->getPointInTime());
    }

    #[Test]
    public function point_in_time_with_keep_alive(): void
    {
        $builder = $this->createBuilder();
        $builder->pointInTime('pit-id-456', '5m');

        $this->assertSame([
            'id' => 'pit-id-456',
            'keep_alive' => '5m',
        ], $builder->getPointInTime());
    }

    #[Test]
    public function clear_point_in_time(): void
    {
        $builder = $this->createBuilder();
        $builder->pointInTime('pit-id');
        $result = $builder->clearPointInTime();

        $this->assertSame($builder, $result);
        $this->assertNull($builder->getPointInTime());
    }

    #[Test]
    public function search_after(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->searchAfter([1609459200000, 'doc-123']);

        $this->assertSame($builder, $result);
        $this->assertSame([1609459200000, 'doc-123'], $builder->getSearchAfter());
    }

    #[Test]
    public function clear_search_after(): void
    {
        $builder = $this->createBuilder();
        $builder->searchAfter([1, 2]);
        $result = $builder->clearSearchAfter();

        $this->assertSame($builder, $result);
        $this->assertNull($builder->getSearchAfter());
    }

    #[Test]
    public function routing_string(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->routing('user-123');

        $this->assertSame($builder, $result);
        $this->assertSame(['user-123'], $builder->getRouting());
    }

    #[Test]
    public function routing_int(): void
    {
        $builder = $this->createBuilder();
        $builder->routing(42);

        $this->assertSame(['42'], $builder->getRouting());
    }

    #[Test]
    public function routing_array(): void
    {
        $builder = $this->createBuilder();
        $builder->routing(['shard1', 'shard2']);

        $this->assertSame(['shard1', 'shard2'], $builder->getRouting());
    }

    #[Test]
    public function routing_null(): void
    {
        $builder = $this->createBuilder();
        $builder->routing('user-123');
        $builder->routing(null);

        $this->assertNull($builder->getRouting());
    }

    #[Test]
    public function clear_routing(): void
    {
        $builder = $this->createBuilder();
        $builder->routing('user-123');
        $result = $builder->clearRouting();

        $this->assertSame($builder, $result);
        $this->assertNull($builder->getRouting());
    }

    #[Test]
    public function explain(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->explain(true);

        $this->assertSame($builder, $result);
        $this->assertTrue($builder->getExplain());
    }

    #[Test]
    public function timeout(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->timeout('30s');

        $this->assertSame($builder, $result);
        $this->assertSame('30s', $builder->getTimeout());
    }

    // ---- Getters default values ----

    #[Test]
    public function getters_return_null_by_default(): void
    {
        $builder = $this->createBuilder();

        $this->assertNull($builder->getQuery());
        $this->assertNull($builder->getFrom());
        $this->assertNull($builder->getSize());
        $this->assertNull($builder->getSource());
        $this->assertNull($builder->getPostFilter());
        $this->assertNull($builder->getTrackTotalHits());
        $this->assertNull($builder->getTrackScores());
        $this->assertNull($builder->getMinScore());
        $this->assertNull($builder->getSearchType());
        $this->assertNull($builder->getPreference());
        $this->assertNull($builder->getPointInTime());
        $this->assertNull($builder->getSearchAfter());
        $this->assertNull($builder->getRouting());
        $this->assertNull($builder->getExplain());
        $this->assertNull($builder->getTimeout());
        $this->assertNull($builder->getKnn());
    }

    #[Test]
    public function getters_return_empty_arrays_by_default(): void
    {
        $builder = $this->createBuilder();

        $this->assertSame([], $builder->getHighlight());
        $this->assertSame([], $builder->getSort());
        $this->assertSame([], $builder->getRescore());
        $this->assertSame([], $builder->getSuggest());
        $this->assertSame([], $builder->getCollapse());
        $this->assertSame([], $builder->getAggregations());
    }

    // ---- Bool query methods ----

    #[Test]
    public function bool_query_creates_and_returns_instance(): void
    {
        $builder = $this->createBuilder();
        $boolQuery = $builder->boolQuery();

        $this->assertInstanceOf(BoolQuery::class, $boolQuery);
        $this->assertSame($boolQuery, $builder->boolQuery());
    }

    #[Test]
    public function has_bool_query_false_by_default(): void
    {
        $builder = $this->createBuilder();
        $this->assertFalse($builder->hasBoolQuery());
    }

    #[Test]
    public function has_bool_query_true_after_access(): void
    {
        $builder = $this->createBuilder();
        $builder->boolQuery();
        $this->assertTrue($builder->hasBoolQuery());
    }

    #[Test]
    public function get_bool_query_null_by_default(): void
    {
        $builder = $this->createBuilder();
        $this->assertNull($builder->getBoolQuery());
    }

    #[Test]
    public function get_bool_query_returns_instance(): void
    {
        $builder = $this->createBuilder();
        $expected = $builder->boolQuery();
        $this->assertSame($expected, $builder->getBoolQuery());
    }

    #[Test]
    public function clear_bool_query(): void
    {
        $builder = $this->createBuilder();
        $builder->boolQuery();
        $result = $builder->clearBoolQuery();

        $this->assertSame($builder, $result);
        $this->assertFalse($builder->hasBoolQuery());
        $this->assertNull($builder->getBoolQuery());
    }

    #[Test]
    public function must_adds_to_bool_query(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->must(new TermQuery('status', 'active'));

        $this->assertSame($builder, $result);
        $this->assertTrue($builder->hasBoolQuery());
        $this->assertCount(1, $builder->getBoolQuery()->getMustClauses());
    }

    #[Test]
    public function must_not_adds_to_bool_query(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->mustNot(new TermQuery('status', 'deleted'));

        $this->assertSame($builder, $result);
        $this->assertCount(1, $builder->getBoolQuery()->getMustNotClauses());
    }

    #[Test]
    public function should_adds_to_bool_query(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->should(new MatchQuery('title', 'test'));

        $this->assertSame($builder, $result);
        $this->assertCount(1, $builder->getBoolQuery()->getShouldClauses());
    }

    #[Test]
    public function filter_adds_to_bool_query(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->filter(new TermQuery('type', 'book'));

        $this->assertSame($builder, $result);
        $this->assertCount(1, $builder->getBoolQuery()->getFilterClauses());
    }

    // ---- clearAll method ----

    #[Test]
    public function clear_all_resets_everything(): void
    {
        $builder = $this->createBuilder();

        $builder->query(['match_all' => new \stdClass()]);
        $builder->must(new TermQuery('status', 'active'));
        $builder->highlight('title');
        $builder->sort('created_at');
        $builder->rescore(['match_all' => new \stdClass()]);
        $builder->from(10);
        $builder->size(20);
        $builder->suggest('test', ['text' => 'x', 'term' => ['field' => 'f']]);
        $builder->source(['title']);
        $builder->collapse('category');
        $builder->aggregate('count', ['value_count' => ['field' => 'id']]);
        $builder->postFilter(['term' => ['x' => 'y']]);
        $builder->trackTotalHits(true);
        $builder->trackScores(true);
        $builder->minScore(1.0);
        $builder->pointInTime('pit-id');
        $builder->searchAfter([1]);
        $builder->routing('r1');
        $builder->explain(true);
        $builder->timeout('10s');
        $builder->knn('embedding', [0.1], k: 5);

        $result = $builder->clearAll();

        $this->assertSame($builder, $result);
        $this->assertNull($builder->getQuery());
        $this->assertFalse($builder->hasBoolQuery());
        $this->assertSame([], $builder->getHighlight());
        $this->assertSame([], $builder->getSort());
        $this->assertSame([], $builder->getRescore());
        $this->assertNull($builder->getFrom());
        $this->assertNull($builder->getSize());
        $this->assertSame([], $builder->getSuggest());
        $this->assertNull($builder->getSource());
        $this->assertSame([], $builder->getCollapse());
        $this->assertSame([], $builder->getAggregations());
        $this->assertNull($builder->getPostFilter());
        $this->assertNull($builder->getTrackTotalHits());
        $this->assertNull($builder->getTrackScores());
        $this->assertNull($builder->getMinScore());
        $this->assertNull($builder->getPointInTime());
        $this->assertNull($builder->getSearchAfter());
        $this->assertNull($builder->getRouting());
        $this->assertNull($builder->getExplain());
        $this->assertNull($builder->getTimeout());
        $this->assertNull($builder->getKnn());
    }

    // ---- Multiple sorts accumulate ----

    #[Test]
    public function multiple_sorts_accumulate(): void
    {
        $builder = $this->createBuilder();
        $builder->sort('date', 'desc');
        $builder->sort('_score');

        $this->assertSame([
            ['date' => 'desc'],
            ['_score' => 'asc'],
        ], $builder->getSort());
    }

    // ---- Multiple highlights accumulate ----

    #[Test]
    public function multiple_highlights_accumulate(): void
    {
        $builder = $this->createBuilder();
        $builder->highlight('title');
        $builder->highlight('description', fragmentSize: 200);

        $highlight = $builder->getHighlight();
        $this->assertArrayHasKey('title', $highlight['fields']);
        $this->assertArrayHasKey('description', $highlight['fields']);
        $this->assertSame(200, $highlight['fields']['description']['fragment_size']);
    }

    // ---- Multiple aggregations accumulate ----

    #[Test]
    public function multiple_aggregations_accumulate(): void
    {
        $builder = $this->createBuilder();
        $builder->aggregate('avg_price', ['avg' => ['field' => 'price']]);
        $builder->aggregate('max_price', ['max' => ['field' => 'price']]);

        $aggs = $builder->getAggregations();
        $this->assertArrayHasKey('avg_price', $aggs);
        $this->assertArrayHasKey('max_price', $aggs);
    }

    // ---- Multiple suggests accumulate ----

    #[Test]
    public function multiple_suggests_accumulate(): void
    {
        $builder = $this->createBuilder();
        $builder->suggest('title-suggest', ['text' => 'test', 'term' => ['field' => 'title']]);
        $builder->suggest('body-suggest', ['text' => 'test', 'term' => ['field' => 'body']]);

        $suggest = $builder->getSuggest();
        $this->assertArrayHasKey('title-suggest', $suggest);
        $this->assertArrayHasKey('body-suggest', $suggest);
    }

    // ---- Additional search options ----

    #[Test]
    public function terminate_after(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->terminateAfter(1000);

        $this->assertSame($builder, $result);
        $this->assertSame(1000, $this->getPrivateProperty($builder, 'terminateAfter'));
    }

    #[Test]
    public function request_cache(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->requestCache(true);

        $this->assertSame($builder, $result);
        $this->assertTrue($this->getPrivateProperty($builder, 'requestCache'));
    }

    #[Test]
    public function stored_fields(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->storedFields(['title', 'date']);

        $this->assertSame($builder, $result);
        $this->assertSame(['title', 'date'], $this->getPrivateProperty($builder, 'storedFields'));
    }

    #[Test]
    public function docvalue_fields(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->docvalueFields(['date', ['field' => 'price', 'format' => 'use_field_mapping']]);

        $this->assertSame($builder, $result);
        $this->assertSame(
            ['date', ['field' => 'price', 'format' => 'use_field_mapping']],
            $this->getPrivateProperty($builder, 'docvalueFields')
        );
    }

    #[Test]
    public function version(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->version();

        $this->assertSame($builder, $result);
        $this->assertTrue($this->getPrivateProperty($builder, 'version'));
    }

    #[Test]
    public function version_false(): void
    {
        $builder = $this->createBuilder();
        $builder->version(false);

        $this->assertFalse($this->getPrivateProperty($builder, 'version'));
    }

    #[Test]
    public function script_fields(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->scriptFields([
            'doubled_price' => ['script' => ['source' => 'doc.price.value * 2']],
        ]);

        $this->assertSame($builder, $result);
        $this->assertSame(
            ['doubled_price' => ['script' => ['source' => 'doc.price.value * 2']]],
            $this->getPrivateProperty($builder, 'scriptFields')
        );
    }

    #[Test]
    public function runtime_mappings(): void
    {
        $builder = $this->createBuilder();
        $result = $builder->runtimeMappings([
            'day_of_week' => [
                'type' => 'keyword',
                'script' => ['source' => "emit(doc['timestamp'].value.dayOfWeekEnum.getDisplayName(TextStyle.FULL, Locale.ROOT))"],
            ],
        ]);

        $this->assertSame($builder, $result);
        $this->assertNotNull($this->getPrivateProperty($builder, 'runtimeMappings'));
    }

    // ---- Get index names ----

    #[Test]
    public function get_index_names(): void
    {
        $builder = $this->createBuilder();

        $this->assertSame(['TestModel' => 'test_index'], $builder->getIndexNames());
    }

    // ---- Edge cases ----

    #[Test]
    public function query_overwrites_previous_query(): void
    {
        $builder = $this->createBuilder();
        $builder->query(['match' => ['title' => 'first']]);
        $builder->query(['match' => ['title' => 'second']]);

        $this->assertSame(['match' => ['title' => 'second']], $builder->getQuery());
    }

    #[Test]
    public function highlight_global_partial_options(): void
    {
        $builder = $this->createBuilder();
        $builder->highlightGlobal(fragmentSize: 100);

        $highlight = $builder->getHighlight();
        $this->assertSame(100, $highlight['fragment_size']);
        $this->assertArrayNotHasKey('number_of_fragments', $highlight);
        $this->assertArrayNotHasKey('pre_tags', $highlight);
    }

    #[Test]
    public function sort_with_missing_only(): void
    {
        $builder = $this->createBuilder();
        $builder->sort('price', 'asc', missing: '_last');

        $this->assertSame([[
            'price' => [
                'order' => 'asc',
                'missing' => '_last',
            ],
        ]], $builder->getSort());
    }

    #[Test]
    public function source_empty_includes(): void
    {
        $builder = $this->createBuilder();
        $builder->source([]);

        $this->assertSame([], $builder->getSource());
    }

    #[Test]
    public function collapse_raw_overwrites_simple(): void
    {
        $builder = $this->createBuilder();
        $builder->collapse('category');
        $builder->collapseRaw(['field' => 'author', 'inner_hits' => []]);

        $this->assertSame(['field' => 'author', 'inner_hits' => []], $builder->getCollapse());
    }

    #[Test]
    public function aggregate_overwrites_same_name(): void
    {
        $builder = $this->createBuilder();
        $builder->aggregate('test', ['avg' => ['field' => 'price']]);
        $builder->aggregate('test', ['sum' => ['field' => 'price']]);

        $this->assertSame(['test' => ['sum' => ['field' => 'price']]], $builder->getAggregations());
    }

    #[Test]
    public function rescore_overwrites_previous(): void
    {
        $builder = $this->createBuilder();
        $builder->rescore(['match' => ['title' => 'first']]);
        $builder->rescore(['match' => ['title' => 'second']]);

        $rescore = $builder->getRescore();
        $this->assertSame(['match' => ['title' => 'second']], $rescore['query']['rescore_query']);
    }

    #[Test]
    public function knn_num_candidates_default_calculation(): void
    {
        $builder = $this->createBuilder();

        $builder->knn('embedding', [0.1, 0.2], k: 5);
        $this->assertSame(100, $builder->getKnn()['num_candidates']);

        $builder->knn('embedding', [0.1, 0.2], k: 100);
        $this->assertSame(200, $builder->getKnn()['num_candidates']);
    }

    #[Test]
    public function post_filter_with_closure(): void
    {
        $builder = $this->createBuilder();
        $builder->postFilter(fn() => new TermQuery('status', 'active'));

        $this->assertSame(['term' => ['status' => ['value' => 'active']]], $builder->getPostFilter());
    }

    #[Test]
    public function routing_converts_int_to_string_array(): void
    {
        $builder = $this->createBuilder();
        $builder->routing(123);

        $this->assertSame(['123'], $builder->getRouting());
    }

    #[Test]
    public function search_after_with_mixed_values(): void
    {
        $builder = $this->createBuilder();
        $builder->searchAfter([1609459200000, 'doc-123', 3.14, null]);

        $this->assertSame([1609459200000, 'doc-123', 3.14, null], $builder->getSearchAfter());
    }

    #[Test]
    public function point_in_time_without_keep_alive(): void
    {
        $builder = $this->createBuilder();
        $builder->pointInTime('pit-id-only');

        $pit = $builder->getPointInTime();
        $this->assertArrayHasKey('id', $pit);
        $this->assertArrayNotHasKey('keep_alive', $pit);
    }

    #[Test]
    public function must_with_multiple_queries_variadic(): void
    {
        $builder = $this->createBuilder();
        $builder->must(
            new TermQuery('status', 'active'),
            new TermQuery('type', 'book'),
            ['match' => ['title' => 'test']]
        );

        $this->assertCount(3, $builder->getBoolQuery()->getMustClauses());
    }

    #[Test]
    public function filter_with_closure(): void
    {
        $builder = $this->createBuilder();
        $builder->filter(fn() => new TermQuery('status', 'published'));

        $this->assertCount(1, $builder->getBoolQuery()->getFilterClauses());
    }

    #[Test]
    public function clear_all_preserves_index_names(): void
    {
        $builder = $this->createBuilder();
        $originalIndexNames = $builder->getIndexNames();

        $builder->query(['match_all' => new \stdClass()]);
        $builder->clearAll();

        $this->assertSame($originalIndexNames, $builder->getIndexNames());
    }

    #[Test]
    public function highlight_same_field_overwrites(): void
    {
        $builder = $this->createBuilder();
        $builder->highlight('title', fragmentSize: 100);
        $builder->highlight('title', fragmentSize: 200);

        $highlight = $builder->getHighlight();
        $this->assertSame(200, $highlight['fields']['title']['fragment_size']);
    }

    #[Test]
    public function suggest_same_name_overwrites(): void
    {
        $builder = $this->createBuilder();
        $builder->suggest('test', ['text' => 'first', 'term' => ['field' => 'title']]);
        $builder->suggest('test', ['text' => 'second', 'term' => ['field' => 'body']]);

        $suggest = $builder->getSuggest();
        $this->assertSame('second', $suggest['test']['text']);
        $this->assertSame('body', $suggest['test']['term']['field']);
    }

    #[Test]
    public function clear_methods_are_idempotent(): void
    {
        $builder = $this->createBuilder();

        $builder->clearQuery();
        $builder->clearQuery();
        $this->assertNull($builder->getQuery());

        $builder->clearHighlight();
        $builder->clearHighlight();
        $this->assertSame([], $builder->getHighlight());

        $builder->clearSort();
        $builder->clearSort();
        $this->assertSame([], $builder->getSort());

        $builder->clearBoolQuery();
        $builder->clearBoolQuery();
        $this->assertNull($builder->getBoolQuery());
    }

    #[Test]
    public function track_total_hits_false(): void
    {
        $builder = $this->createBuilder();
        $builder->trackTotalHits(false);

        $this->assertFalse($builder->getTrackTotalHits());
    }

    #[Test]
    public function explain_false(): void
    {
        $builder = $this->createBuilder();
        $builder->explain(false);

        $this->assertFalse($builder->getExplain());
    }

    #[Test]
    public function track_scores_false(): void
    {
        $builder = $this->createBuilder();
        $builder->trackScores(false);

        $this->assertFalse($builder->getTrackScores());
    }

    #[Test]
    public function min_score_zero(): void
    {
        $builder = $this->createBuilder();
        $builder->minScore(0.0);

        $this->assertSame(0.0, $builder->getMinScore());
    }

    #[Test]
    public function from_zero(): void
    {
        $builder = $this->createBuilder();
        $builder->from(0);

        $this->assertSame(0, $builder->getFrom());
    }

    #[Test]
    public function size_zero(): void
    {
        $builder = $this->createBuilder();
        $builder->size(0);

        $this->assertSame(0, $builder->getSize());
    }
}
