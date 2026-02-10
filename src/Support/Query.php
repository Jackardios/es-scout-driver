<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Support;

use Closure;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\Compound\BoostingQuery;
use Jackardios\EsScoutDriver\Query\Compound\ConstantScoreQuery;
use Jackardios\EsScoutDriver\Query\Compound\DisMaxQuery;
use Jackardios\EsScoutDriver\Query\Compound\FunctionScoreQuery;
use Jackardios\EsScoutDriver\Query\Compound\NestedQuery;
use Jackardios\EsScoutDriver\Query\Joining\HasChildQuery;
use Jackardios\EsScoutDriver\Query\Joining\HasParentQuery;
use Jackardios\EsScoutDriver\Query\Joining\ParentIdQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchPhrasePrefixQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchPhraseQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Query\FullText\MultiMatchQuery;
use Jackardios\EsScoutDriver\Query\FullText\QueryStringQuery;
use Jackardios\EsScoutDriver\Query\FullText\SimpleQueryStringQuery;
use Jackardios\EsScoutDriver\Query\Geo\GeoBoundingBoxQuery;
use Jackardios\EsScoutDriver\Query\Geo\GeoDistanceQuery;
use Jackardios\EsScoutDriver\Query\Geo\GeoShapeQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\RawQuery;
use Jackardios\EsScoutDriver\Query\Specialized\KnnQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchNoneQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MoreLikeThisQuery;
use Jackardios\EsScoutDriver\Query\Specialized\PinnedQuery;
use Jackardios\EsScoutDriver\Query\Specialized\ScriptScoreQuery;
use Jackardios\EsScoutDriver\Query\Specialized\SemanticQuery;
use Jackardios\EsScoutDriver\Query\Specialized\SparseVectorQuery;
use Jackardios\EsScoutDriver\Query\Specialized\TextExpansionQuery;
use Jackardios\EsScoutDriver\Query\Term\ExistsQuery;
use Jackardios\EsScoutDriver\Query\Term\FuzzyQuery;
use Jackardios\EsScoutDriver\Query\Term\IdsQuery;
use Jackardios\EsScoutDriver\Query\Term\PrefixQuery;
use Jackardios\EsScoutDriver\Query\Term\RangeQuery;
use Jackardios\EsScoutDriver\Query\Term\RegexpQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Query\Term\TermsQuery;
use Jackardios\EsScoutDriver\Query\Term\WildcardQuery;
use Illuminate\Support\Traits\Macroable;

/**
 * Static factory for creating query objects.
 *
 * Required parameters are passed to constructors, optional via fluent setters:
 *   Query::match('title', 'test')->fuzziness('AUTO')
 */
final class Query
{
    use Macroable;

    /** @param array<string, mixed> $query */
    public static function raw(array $query): RawQuery
    {
        return new RawQuery($query);
    }

    public static function term(string $field, string|int|float|bool $value): TermQuery
    {
        return new TermQuery($field, $value);
    }

    /** @param array<int, string|int|float|bool> $values */
    public static function terms(string $field, array $values): TermsQuery
    {
        return new TermsQuery($field, $values);
    }

    public static function range(string $field): RangeQuery
    {
        return new RangeQuery($field);
    }

    public static function exists(string $field): ExistsQuery
    {
        return new ExistsQuery($field);
    }

    public static function prefix(string $field, string $value): PrefixQuery
    {
        return new PrefixQuery($field, $value);
    }

    public static function wildcard(string $field, string $value): WildcardQuery
    {
        return new WildcardQuery($field, $value);
    }

    public static function regexp(string $field, string $value): RegexpQuery
    {
        return new RegexpQuery($field, $value);
    }

    public static function fuzzy(string $field, string $value): FuzzyQuery
    {
        return new FuzzyQuery($field, $value);
    }

    /** @param array<int, string> $values */
    public static function ids(array $values): IdsQuery
    {
        return new IdsQuery($values);
    }

    public static function match(string $field, string|int|float|bool $query): MatchQuery
    {
        return new MatchQuery($field, $query);
    }

    /** @param array<int, string> $fields */
    public static function multiMatch(array $fields, string|int|float|bool $query): MultiMatchQuery
    {
        return new MultiMatchQuery($fields, $query);
    }

    public static function matchPhrase(string $field, string $query): MatchPhraseQuery
    {
        return new MatchPhraseQuery($field, $query);
    }

    public static function matchPhrasePrefix(string $field, string $query): MatchPhrasePrefixQuery
    {
        return new MatchPhrasePrefixQuery($field, $query);
    }

    public static function queryString(string $query): QueryStringQuery
    {
        return new QueryStringQuery($query);
    }

    public static function simpleQueryString(string $query): SimpleQueryStringQuery
    {
        return new SimpleQueryStringQuery($query);
    }

    public static function geoDistance(string $field, float $lat, float $lon, string $distance): GeoDistanceQuery
    {
        return new GeoDistanceQuery($field, $lat, $lon, $distance);
    }

    public static function geoBoundingBox(
        string $field,
        float $topLeftLat,
        float $topLeftLon,
        float $bottomRightLat,
        float $bottomRightLon,
    ): GeoBoundingBoxQuery {
        return new GeoBoundingBoxQuery($field, $topLeftLat, $topLeftLon, $bottomRightLat, $bottomRightLon);
    }

    public static function geoShape(string $field): GeoShapeQuery
    {
        return new GeoShapeQuery($field);
    }

    public static function matchAll(): MatchAllQuery
    {
        return new MatchAllQuery();
    }

    public static function matchNone(): MatchNoneQuery
    {
        return new MatchNoneQuery();
    }

    /**
     * @param array<int, string> $fields
     * @param string|array<int, string|array<string, mixed>> $like
     */
    public static function moreLikeThis(array $fields, string|array $like): MoreLikeThisQuery
    {
        return new MoreLikeThisQuery($fields, $like);
    }

    /** @param array<string, mixed> $script */
    public static function scriptScore(QueryInterface|array $query, array $script): ScriptScoreQuery
    {
        return new ScriptScoreQuery($query, $script);
    }

    public static function bool(): BoolQuery
    {
        return new BoolQuery();
    }

    /** @param QueryInterface|Closure|array $query */
    public static function nested(string $path, QueryInterface|Closure|array $query): NestedQuery
    {
        return new NestedQuery($path, $query);
    }

    public static function functionScore(QueryInterface|array|null $query = null): FunctionScoreQuery
    {
        return new FunctionScoreQuery($query);
    }

    /** @param array<QueryInterface|array> $queries */
    public static function disMax(array $queries = []): DisMaxQuery
    {
        return new DisMaxQuery($queries);
    }

    public static function boosting(QueryInterface|array $positive, QueryInterface|array $negative): BoostingQuery
    {
        return new BoostingQuery($positive, $negative);
    }

    /** @param QueryInterface|Closure|array $filter */
    public static function constantScore(QueryInterface|Closure|array $filter): ConstantScoreQuery
    {
        return new ConstantScoreQuery($filter);
    }

    /** @param QueryInterface|Closure|array $query */
    public static function hasChild(string $type, QueryInterface|Closure|array $query): HasChildQuery
    {
        return new HasChildQuery($type, $query);
    }

    /** @param QueryInterface|Closure|array $query */
    public static function hasParent(string $parentType, QueryInterface|Closure|array $query): HasParentQuery
    {
        return new HasParentQuery($parentType, $query);
    }

    public static function parentId(string $type, string $id): ParentIdQuery
    {
        return new ParentIdQuery($type, $id);
    }

    /** @param array<int, float> $queryVector */
    public static function knn(string $field, array $queryVector, int $k): KnnQuery
    {
        return new KnnQuery($field, $queryVector, $k);
    }

    public static function sparseVector(string $field): SparseVectorQuery
    {
        return new SparseVectorQuery($field);
    }

    public static function pinned(QueryInterface|array $organic): PinnedQuery
    {
        return new PinnedQuery($organic);
    }

    /**
     * Semantic query for semantic text fields (ES 8.14+).
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-semantic-query.html
     */
    public static function semantic(string $field, string $query): SemanticQuery
    {
        return new SemanticQuery($field, $query);
    }

    /**
     * Text expansion query for rank features or sparse vectors (ES 8.8+).
     *
     * Note: Deprecated in favor of sparseVector() in ES 8.15+.
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-text-expansion-query.html
     */
    public static function textExpansion(string $field, string $modelId): TextExpansionQuery
    {
        return new TextExpansionQuery($field, $modelId);
    }
}
