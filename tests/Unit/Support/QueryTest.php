<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Support;

use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\Compound\BoostingQuery;
use Jackardios\EsScoutDriver\Query\Compound\DisMaxQuery;
use Jackardios\EsScoutDriver\Query\Compound\FunctionScoreQuery;
use Jackardios\EsScoutDriver\Query\Compound\NestedQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchPhrasePrefixQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchPhraseQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Query\FullText\MultiMatchQuery;
use Jackardios\EsScoutDriver\Query\FullText\QueryStringQuery;
use Jackardios\EsScoutDriver\Query\FullText\SimpleQueryStringQuery;
use Jackardios\EsScoutDriver\Query\Geo\GeoBoundingBoxQuery;
use Jackardios\EsScoutDriver\Query\Geo\GeoDistanceQuery;
use Jackardios\EsScoutDriver\Query\RawQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchNoneQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MoreLikeThisQuery;
use Jackardios\EsScoutDriver\Query\Specialized\ScriptScoreQuery;
use Jackardios\EsScoutDriver\Query\Term\ExistsQuery;
use Jackardios\EsScoutDriver\Query\Term\FuzzyQuery;
use Jackardios\EsScoutDriver\Query\Term\IdsQuery;
use Jackardios\EsScoutDriver\Query\Term\PrefixQuery;
use Jackardios\EsScoutDriver\Query\Term\RangeQuery;
use Jackardios\EsScoutDriver\Query\Term\RegexpQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Query\Term\TermsQuery;
use Jackardios\EsScoutDriver\Query\Term\WildcardQuery;
use Jackardios\EsScoutDriver\Support\Query;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryTest extends TestCase
{
    #[Test]
    public function raw_returns_raw_query(): void
    {
        $result = Query::raw(['match' => ['title' => 'test']]);

        $this->assertInstanceOf(RawQuery::class, $result);
    }

    #[Test]
    public function term_returns_term_query(): void
    {
        $result = Query::term('status', 'active');

        $this->assertInstanceOf(TermQuery::class, $result);
        $this->assertSame([
            'term' => ['status' => ['value' => 'active']],
        ], $result->toArray());
    }

    #[Test]
    public function terms_returns_terms_query(): void
    {
        $result = Query::terms('status', ['active', 'pending']);

        $this->assertInstanceOf(TermsQuery::class, $result);
        $this->assertSame([
            'terms' => ['status' => ['active', 'pending']],
        ], $result->toArray());
    }

    #[Test]
    public function terms_with_boost(): void
    {
        $result = Query::terms('status', ['active'])->boost(1.5);

        $this->assertSame(1.5, $result->toArray()['terms']['boost']);
    }

    #[Test]
    public function range_returns_range_query(): void
    {
        $result = Query::range('age');

        $this->assertInstanceOf(RangeQuery::class, $result);
    }

    #[Test]
    public function exists_returns_exists_query(): void
    {
        $result = Query::exists('email');

        $this->assertInstanceOf(ExistsQuery::class, $result);
        $this->assertSame([
            'exists' => ['field' => 'email'],
        ], $result->toArray());
    }

    #[Test]
    public function prefix_returns_prefix_query(): void
    {
        $result = Query::prefix('username', 'joh');

        $this->assertInstanceOf(PrefixQuery::class, $result);
        $this->assertSame([
            'prefix' => ['username' => ['value' => 'joh']],
        ], $result->toArray());
    }

    #[Test]
    public function wildcard_returns_wildcard_query(): void
    {
        $result = Query::wildcard('name', 'jo*');

        $this->assertInstanceOf(WildcardQuery::class, $result);
        $this->assertSame([
            'wildcard' => ['name' => ['value' => 'jo*']],
        ], $result->toArray());
    }

    #[Test]
    public function regexp_returns_regexp_query(): void
    {
        $result = Query::regexp('name', 'joh.*');

        $this->assertInstanceOf(RegexpQuery::class, $result);
        $this->assertSame([
            'regexp' => ['name' => ['value' => 'joh.*']],
        ], $result->toArray());
    }

    #[Test]
    public function fuzzy_returns_fuzzy_query(): void
    {
        $result = Query::fuzzy('title', 'test');

        $this->assertInstanceOf(FuzzyQuery::class, $result);
        $this->assertSame([
            'fuzzy' => ['title' => ['value' => 'test']],
        ], $result->toArray());
    }

    #[Test]
    public function ids_returns_ids_query(): void
    {
        $result = Query::ids(['1', '2', '3']);

        $this->assertInstanceOf(IdsQuery::class, $result);
        $this->assertSame([
            'ids' => ['values' => ['1', '2', '3']],
        ], $result->toArray());
    }

    #[Test]
    public function match_returns_match_query(): void
    {
        $result = Query::match('title', 'search text');

        $this->assertInstanceOf(MatchQuery::class, $result);
        $this->assertSame([
            'match' => ['title' => ['query' => 'search text']],
        ], $result->toArray());
    }

    #[Test]
    public function multi_match_returns_multi_match_query(): void
    {
        $result = Query::multiMatch(['title', 'body'], 'search text');

        $this->assertInstanceOf(MultiMatchQuery::class, $result);
        $this->assertSame([
            'multi_match' => [
                'fields' => ['title', 'body'],
                'query' => 'search text',
            ],
        ], $result->toArray());
    }

    #[Test]
    public function match_phrase_returns_match_phrase_query(): void
    {
        $result = Query::matchPhrase('title', 'quick brown fox');

        $this->assertInstanceOf(MatchPhraseQuery::class, $result);
        $this->assertSame([
            'match_phrase' => ['title' => ['query' => 'quick brown fox']],
        ], $result->toArray());
    }

    #[Test]
    public function match_phrase_prefix_returns_match_phrase_prefix_query(): void
    {
        $result = Query::matchPhrasePrefix('title', 'quick brown f');

        $this->assertInstanceOf(MatchPhrasePrefixQuery::class, $result);
        $this->assertSame([
            'match_phrase_prefix' => ['title' => ['query' => 'quick brown f']],
        ], $result->toArray());
    }

    #[Test]
    public function query_string_returns_query_string_query(): void
    {
        $result = Query::queryString('title:search');

        $this->assertInstanceOf(QueryStringQuery::class, $result);
        $this->assertSame([
            'query_string' => ['query' => 'title:search'],
        ], $result->toArray());
    }

    #[Test]
    public function simple_query_string_returns_simple_query_string_query(): void
    {
        $result = Query::simpleQueryString('search text');

        $this->assertInstanceOf(SimpleQueryStringQuery::class, $result);
        $this->assertSame([
            'simple_query_string' => ['query' => 'search text'],
        ], $result->toArray());
    }

    #[Test]
    public function geo_distance_returns_geo_distance_query(): void
    {
        $result = Query::geoDistance('location', 40.73, -73.99, '10km');

        $this->assertInstanceOf(GeoDistanceQuery::class, $result);
        $this->assertSame([
            'geo_distance' => [
                'location' => ['lat' => 40.73, 'lon' => -73.99],
                'distance' => '10km',
            ],
        ], $result->toArray());
    }

    #[Test]
    public function geo_bounding_box_returns_geo_bounding_box_query(): void
    {
        $result = Query::geoBoundingBox('location', 40.73, -74.1, 40.01, -71.12);

        $this->assertInstanceOf(GeoBoundingBoxQuery::class, $result);
        $this->assertSame([
            'geo_bounding_box' => [
                'location' => [
                    'top_left' => ['lat' => 40.73, 'lon' => -74.1],
                    'bottom_right' => ['lat' => 40.01, 'lon' => -71.12],
                ],
            ],
        ], $result->toArray());
    }

    #[Test]
    public function match_all_returns_match_all_query(): void
    {
        $result = Query::matchAll();

        $this->assertInstanceOf(MatchAllQuery::class, $result);
    }

    #[Test]
    public function match_all_with_boost(): void
    {
        $result = Query::matchAll()->boost(1.5);

        $this->assertSame([
            'match_all' => ['boost' => 1.5],
        ], $result->toArray());
    }

    #[Test]
    public function match_none_returns_match_none_query(): void
    {
        $result = Query::matchNone();

        $this->assertInstanceOf(MatchNoneQuery::class, $result);
    }

    #[Test]
    public function more_like_this_returns_more_like_this_query(): void
    {
        $result = Query::moreLikeThis(['title'], 'some text');

        $this->assertInstanceOf(MoreLikeThisQuery::class, $result);
        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'some text',
            ],
        ], $result->toArray());
    }

    #[Test]
    public function script_score_returns_script_score_query(): void
    {
        $result = Query::scriptScore(['match_all' => []], ['source' => '_score * 2']);

        $this->assertInstanceOf(ScriptScoreQuery::class, $result);
        $this->assertSame([
            'script_score' => [
                'query' => ['match_all' => []],
                'script' => ['source' => '_score * 2'],
            ],
        ], $result->toArray());
    }

    #[Test]
    public function bool_returns_bool_query(): void
    {
        $result = Query::bool();

        $this->assertInstanceOf(BoolQuery::class, $result);
    }

    #[Test]
    public function nested_returns_nested_query(): void
    {
        $result = Query::nested('comments', ['match' => ['comments.body' => 'great']]);

        $this->assertInstanceOf(NestedQuery::class, $result);
        $this->assertSame([
            'nested' => [
                'path' => 'comments',
                'query' => ['match' => ['comments.body' => 'great']],
            ],
        ], $result->toArray());
    }

    #[Test]
    public function function_score_returns_function_score_query(): void
    {
        $result = Query::functionScore();

        $this->assertInstanceOf(FunctionScoreQuery::class, $result);
    }

    #[Test]
    public function dis_max_returns_dis_max_query(): void
    {
        $result = Query::disMax();

        $this->assertInstanceOf(DisMaxQuery::class, $result);
    }

    #[Test]
    public function boosting_returns_boosting_query(): void
    {
        $result = Query::boosting(
            Query::match('title', 'search'),
            Query::term('status', 'spam')
        );

        $this->assertInstanceOf(BoostingQuery::class, $result);
    }
}
