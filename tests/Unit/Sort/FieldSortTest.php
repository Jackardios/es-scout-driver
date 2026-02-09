<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Sort;

use Jackardios\EsScoutDriver\Enums\SortOrder;
use Jackardios\EsScoutDriver\Sort\FieldSort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FieldSortTest extends TestCase
{
    #[Test]
    public function it_builds_basic_field_sort_asc(): void
    {
        $sort = new FieldSort('price');

        $this->assertSame(['price' => 'asc'], $sort->toArray());
    }

    #[Test]
    public function it_builds_field_sort_desc(): void
    {
        $sort = (new FieldSort('price'))->desc();

        $this->assertSame(['price' => 'desc'], $sort->toArray());
    }

    #[Test]
    public function it_builds_field_sort_with_missing(): void
    {
        $sort = (new FieldSort('price'))->desc()->missing('_last');

        $this->assertSame([
            'price' => ['order' => 'desc', 'missing' => '_last'],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_field_sort_with_all_options(): void
    {
        $sort = (new FieldSort('price'))
            ->desc()
            ->missing('_last')
            ->mode('avg')
            ->unmappedType('float');

        $this->assertSame([
            'price' => [
                'order' => 'desc',
                'missing' => '_last',
                'mode' => 'avg',
                'unmapped_type' => 'float',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_accepts_sort_order_enum(): void
    {
        $sort = (new FieldSort('price'))->order(SortOrder::Desc);

        $this->assertSame(['price' => 'desc'], $sort->toArray());
    }

    #[Test]
    public function it_has_missing_first_and_last_helpers(): void
    {
        $sort1 = (new FieldSort('price'))->missingFirst();
        $sort2 = (new FieldSort('price'))->missingLast();

        $this->assertSame(['price' => ['order' => 'asc', 'missing' => '_first']], $sort1->toArray());
        $this->assertSame(['price' => ['order' => 'asc', 'missing' => '_last']], $sort2->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $sort = new FieldSort('price');

        $this->assertSame($sort, $sort->asc());
        $this->assertSame($sort, $sort->desc());
        $this->assertSame($sort, $sort->missing('_last'));
        $this->assertSame($sort, $sort->mode('avg'));
        $this->assertSame($sort, $sort->unmappedType('float'));
    }
}
