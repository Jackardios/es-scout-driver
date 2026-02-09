<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Sort;

use Jackardios\EsScoutDriver\Enums\SortOrder;
use Jackardios\EsScoutDriver\Sort\ScriptSort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScriptSortTest extends TestCase
{
    #[Test]
    public function it_builds_basic_script_sort(): void
    {
        $sort = new ScriptSort(
            ['source' => "doc['price'].value * params.factor", 'params' => ['factor' => 1.1]],
            'number',
        );

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => [
                    'source' => "doc['price'].value * params.factor",
                    'params' => ['factor' => 1.1],
                ],
                'order' => 'asc',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_script_sort_desc(): void
    {
        $sort = (new ScriptSort(['source' => '_score * 2'], 'number'))->desc();

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => '_score * 2'],
                'order' => 'desc',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_script_sort_with_mode(): void
    {
        $sort = (new ScriptSort(['source' => '_score'], 'number'))
            ->mode('avg');

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => '_score'],
                'order' => 'asc',
                'mode' => 'avg',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $sort = new ScriptSort(['source' => '_score'], 'number');

        $this->assertSame($sort, $sort->asc());
        $this->assertSame($sort, $sort->desc());
        $this->assertSame($sort, $sort->mode('avg'));
    }

    #[Test]
    public function it_builds_script_sort_with_nested(): void
    {
        $sort = (new ScriptSort(['source' => "doc['offer.price'].value"], 'number'))
            ->nested([
                'path' => 'offer',
                'filter' => ['term' => ['offer.active' => true]],
            ])
            ->desc();

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => "doc['offer.price'].value"],
                'order' => 'desc',
                'nested' => [
                    'path' => 'offer',
                    'filter' => ['term' => ['offer.active' => true]],
                ],
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_script_sort_with_nested_path_only(): void
    {
        $sort = (new ScriptSort(['source' => "doc['comments.score'].value"], 'number'))
            ->nested(['path' => 'comments']);

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => "doc['comments.score'].value"],
                'order' => 'asc',
                'nested' => ['path' => 'comments'],
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface_for_nested(): void
    {
        $sort = new ScriptSort(['source' => '_score'], 'number');

        $this->assertSame($sort, $sort->nested(['path' => 'offer']));
    }

    #[Test]
    public function it_builds_script_sort_with_string_type(): void
    {
        $sort = new ScriptSort(
            ['source' => "doc['name'].value.toUpperCase()"],
            'string',
        );

        $this->assertSame([
            '_script' => [
                'type' => 'string',
                'script' => ['source' => "doc['name'].value.toUpperCase()"],
                'order' => 'asc',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_accepts_sort_order_enum(): void
    {
        $sort = (new ScriptSort(['source' => '_score'], 'number'))
            ->order(SortOrder::Desc);

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => '_score'],
                'order' => 'desc',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function order_accepts_string(): void
    {
        $sort = (new ScriptSort(['source' => '_score'], 'number'))
            ->order('desc');

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => '_score'],
                'order' => 'desc',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_script_sort_with_all_options(): void
    {
        $sort = (new ScriptSort(
            ['source' => "doc['offer.price'].value * params.multiplier", 'params' => ['multiplier' => 1.5]],
            'number',
        ))
            ->desc()
            ->mode('min')
            ->nested([
                'path' => 'offer',
                'filter' => ['term' => ['offer.status' => 'active']],
            ]);

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => [
                    'source' => "doc['offer.price'].value * params.multiplier",
                    'params' => ['multiplier' => 1.5],
                ],
                'order' => 'desc',
                'mode' => 'min',
                'nested' => [
                    'path' => 'offer',
                    'filter' => ['term' => ['offer.status' => 'active']],
                ],
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_script_sort_with_mode_sum(): void
    {
        $sort = (new ScriptSort(['source' => "doc['prices'].values.stream().sum()"], 'number'))
            ->mode('sum');

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => "doc['prices'].values.stream().sum()"],
                'order' => 'asc',
                'mode' => 'sum',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_script_sort_with_mode_min(): void
    {
        $sort = (new ScriptSort(['source' => '_score'], 'number'))
            ->mode('min');

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => '_score'],
                'order' => 'asc',
                'mode' => 'min',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_script_sort_with_mode_max(): void
    {
        $sort = (new ScriptSort(['source' => '_score'], 'number'))
            ->mode('max');

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => '_score'],
                'order' => 'asc',
                'mode' => 'max',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_script_sort_with_mode_median(): void
    {
        $sort = (new ScriptSort(['source' => '_score'], 'number'))
            ->mode('median');

        $this->assertSame([
            '_script' => [
                'type' => 'number',
                'script' => ['source' => '_score'],
                'order' => 'asc',
                'mode' => 'median',
            ],
        ], $sort->toArray());
    }
}
