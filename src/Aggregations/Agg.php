<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations;

use Illuminate\Support\Traits\Macroable;
use Jackardios\EsScoutDriver\Aggregations\Bucket\CompositeAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\DateHistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\FilterAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\FiltersAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\GeoDistanceAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\GlobalAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\HistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\NestedAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\RangeAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\ReverseNestedAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\CardinalityAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\ExtendedStatsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\MaxAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\MinAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\PercentilesAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\StatsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\SumAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\GeoBoundsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\GeoCentroidAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\TopHitsAggregation;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * Static factory for creating aggregation objects.
 *
 * Required parameters are passed to constructors, optional via fluent setters:
 *   Agg::terms('author')->size(10)->orderByCount()
 */
final class Agg
{
    use Macroable;

    public static function terms(string $field): TermsAggregation
    {
        return new TermsAggregation($field);
    }

    public static function avg(string $field): AvgAggregation
    {
        return new AvgAggregation($field);
    }

    public static function sum(string $field): SumAggregation
    {
        return new SumAggregation($field);
    }

    public static function min(string $field): MinAggregation
    {
        return new MinAggregation($field);
    }

    public static function max(string $field): MaxAggregation
    {
        return new MaxAggregation($field);
    }

    public static function stats(string $field): StatsAggregation
    {
        return new StatsAggregation($field);
    }

    public static function cardinality(string $field): CardinalityAggregation
    {
        return new CardinalityAggregation($field);
    }

    public static function histogram(string $field, int|float $interval): HistogramAggregation
    {
        return new HistogramAggregation($field, $interval);
    }

    public static function dateHistogram(string $field, string $calendarInterval): DateHistogramAggregation
    {
        return new DateHistogramAggregation($field, $calendarInterval);
    }

    public static function range(string $field): RangeAggregation
    {
        return new RangeAggregation($field);
    }

    public static function percentiles(string $field): PercentilesAggregation
    {
        return new PercentilesAggregation($field);
    }

    public static function extendedStats(string $field): ExtendedStatsAggregation
    {
        return new ExtendedStatsAggregation($field);
    }

    public static function topHits(): TopHitsAggregation
    {
        return new TopHitsAggregation();
    }

    public static function composite(): CompositeAggregation
    {
        return new CompositeAggregation();
    }

    public static function filter(QueryInterface|array $filter): FilterAggregation
    {
        return new FilterAggregation($filter);
    }

    public static function filters(): FiltersAggregation
    {
        return new FiltersAggregation();
    }

    public static function global(): GlobalAggregation
    {
        return new GlobalAggregation();
    }

    public static function nested(string $path): NestedAggregation
    {
        return new NestedAggregation($path);
    }

    public static function reverseNested(): ReverseNestedAggregation
    {
        return new ReverseNestedAggregation();
    }

    public static function geoDistance(string $field, float $lat, float $lon): GeoDistanceAggregation
    {
        return new GeoDistanceAggregation($field, $lat, $lon);
    }

    public static function geoBounds(string $field): GeoBoundsAggregation
    {
        return new GeoBoundsAggregation($field);
    }

    public static function geoCentroid(string $field): GeoCentroidAggregation
    {
        return new GeoCentroidAggregation($field);
    }
}
