<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Sort;

use Jackardios\EsScoutDriver\Sort\FieldSort;
use Jackardios\EsScoutDriver\Sort\GeoDistanceSort;
use Jackardios\EsScoutDriver\Sort\ScoreSort;
use Jackardios\EsScoutDriver\Sort\ScriptSort;
use Jackardios\EsScoutDriver\Sort\Sort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SortFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_field_sort(): void
    {
        $sort = Sort::field('price');
        $this->assertInstanceOf(FieldSort::class, $sort);
        $this->assertSame(['price' => 'asc'], $sort->toArray());
    }

    #[Test]
    public function it_creates_score_sort(): void
    {
        $sort = Sort::score();
        $this->assertInstanceOf(ScoreSort::class, $sort);
        $this->assertSame(['_score' => 'desc'], $sort->toArray());
    }

    #[Test]
    public function it_creates_geo_distance_sort(): void
    {
        $sort = Sort::geoDistance('location', 40.7, -74.0);
        $this->assertInstanceOf(GeoDistanceSort::class, $sort);

        $this->assertSame([
            '_geo_distance' => [
                'location' => ['lat' => 40.7, 'lon' => -74.0],
                'order' => 'asc',
                'unit' => 'km',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_creates_script_sort(): void
    {
        $sort = Sort::script(['source' => '_score * 2'], 'number');
        $this->assertInstanceOf(ScriptSort::class, $sort);

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => '_score * 2'],
                'order' => 'asc',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_supports_fluent_api(): void
    {
        $sort = Sort::field('price')
            ->desc()
            ->missing('_last');

        $this->assertSame([
            'price' => [
                'order' => 'desc',
                'missing' => '_last',
            ],
        ], $sort->toArray());
    }
}
