<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Sort;

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
}
