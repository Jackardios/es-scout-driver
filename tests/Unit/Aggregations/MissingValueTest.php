<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Aggregations;

use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MissingValueTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): AggregationInterface, string}> */
    public static function aggregations(): iterable
    {
        yield 'terms' => [static fn() => Agg::terms('f'), 'terms'];
        yield 'histogram' => [static fn() => Agg::histogram('f', 10), 'histogram'];
        yield 'date_histogram' => [static fn() => Agg::dateHistogram('f', 'month'), 'date_histogram'];
        yield 'range' => [static fn() => Agg::range('f')->range(to: 1), 'range'];
        yield 'avg' => [static fn() => Agg::avg('f'), 'avg'];
        yield 'sum' => [static fn() => Agg::sum('f'), 'sum'];
        yield 'min' => [static fn() => Agg::min('f'), 'min'];
        yield 'max' => [static fn() => Agg::max('f'), 'max'];
        yield 'stats' => [static fn() => Agg::stats('f'), 'stats'];
        yield 'extended_stats' => [static fn() => Agg::extendedStats('f'), 'extended_stats'];
        yield 'cardinality' => [static fn() => Agg::cardinality('f'), 'cardinality'];
        yield 'percentiles' => [static fn() => Agg::percentiles('f'), 'percentiles'];
    }

    #[Test]
    #[DataProvider('aggregations')]
    public function missing_accepts_any_scalar(\Closure $make, string $type): void
    {
        foreach (['n/a', 0, 1.5, true] as $value) {
            $this->assertSame($value, $make()->missing($value)->toArray()[$type]['missing']);
        }
    }
}
