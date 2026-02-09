<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations;

use Illuminate\Support\Traits\Macroable;
use Jackardios\EsScoutDriver\Aggregations\Bucket\DateHistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\HistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\RangeAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\CardinalityAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\MaxAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\MinAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\StatsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\SumAggregation;

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
}
