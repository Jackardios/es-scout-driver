<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort;

use Illuminate\Support\Traits\Macroable;

/**
 * Static factory for creating sort objects.
 *
 * Required parameters are passed to constructors, optional via fluent setters:
 *   Sort::field('created_at')->desc()->missing('_last')
 */
final class Sort
{
    use Macroable;

    public static function field(string $field): FieldSort
    {
        return new FieldSort($field);
    }

    public static function score(): ScoreSort
    {
        return new ScoreSort();
    }

    public static function geoDistance(string $field, float $lat, float $lon): GeoDistanceSort
    {
        return new GeoDistanceSort($field, $lat, $lon);
    }

    public static function script(array $script, string $type): ScriptSort
    {
        return new ScriptSort($script, $type);
    }
}
