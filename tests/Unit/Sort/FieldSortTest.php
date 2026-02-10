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
    public function it_builds_field_sort_with_numeric_missing(): void
    {
        $sort = (new FieldSort('price'))->desc()->missing(0);

        $this->assertSame([
            'price' => ['order' => 'desc', 'missing' => 0],
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

    #[Test]
    public function it_builds_field_sort_with_nested(): void
    {
        $sort = (new FieldSort('offer.price'))
            ->nested([
                'path' => 'offer',
                'filter' => ['term' => ['offer.status' => 'active']],
            ])
            ->desc();

        $this->assertSame([
            'offer.price' => [
                'order' => 'desc',
                'nested' => [
                    'path' => 'offer',
                    'filter' => ['term' => ['offer.status' => 'active']],
                ],
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_field_sort_with_nested_path_only(): void
    {
        $sort = (new FieldSort('comments.stars'))
            ->nested(['path' => 'comments']);

        $this->assertSame([
            'comments.stars' => [
                'order' => 'asc',
                'nested' => ['path' => 'comments'],
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_field_sort_with_numeric_type(): void
    {
        $sort = (new FieldSort('date'))
            ->numericType('date');

        $this->assertSame([
            'date' => [
                'order' => 'asc',
                'numeric_type' => 'date',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_field_sort_with_numeric_type_long(): void
    {
        $sort = (new FieldSort('timestamp'))
            ->numericType('long');

        $this->assertSame([
            'timestamp' => [
                'order' => 'asc',
                'numeric_type' => 'long',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_field_sort_with_format(): void
    {
        $sort = (new FieldSort('date'))
            ->format('strict_date_optional_time_nanos');

        $this->assertSame([
            'date' => [
                'order' => 'asc',
                'format' => 'strict_date_optional_time_nanos',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_builds_field_sort_with_date_format(): void
    {
        $sort = (new FieldSort('created_at'))
            ->format('yyyy-MM-dd');

        $this->assertSame([
            'created_at' => [
                'order' => 'asc',
                'format' => 'yyyy-MM-dd',
            ],
        ], $sort->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface_for_all_methods(): void
    {
        $sort = new FieldSort('price');

        $this->assertSame($sort, $sort->nested(['path' => 'offer']));
        $this->assertSame($sort, $sort->numericType('long'));
        $this->assertSame($sort, $sort->format('yyyy-MM-dd'));
        $this->assertSame($sort, $sort->order('desc'));
    }

    #[Test]
    public function it_builds_field_sort_with_comprehensive_options(): void
    {
        $sort = (new FieldSort('offer.price'))
            ->desc()
            ->missing('_last')
            ->mode('min')
            ->unmappedType('float')
            ->nested([
                'path' => 'offer',
                'filter' => ['term' => ['offer.active' => true]],
            ])
            ->numericType('double')
            ->format('0.00');

        $result = $sort->toArray();

        $this->assertSame('desc', $result['offer.price']['order']);
        $this->assertSame('_last', $result['offer.price']['missing']);
        $this->assertSame('min', $result['offer.price']['mode']);
        $this->assertSame('float', $result['offer.price']['unmapped_type']);
        $this->assertSame(['path' => 'offer', 'filter' => ['term' => ['offer.active' => true]]], $result['offer.price']['nested']);
        $this->assertSame('double', $result['offer.price']['numeric_type']);
        $this->assertSame('0.00', $result['offer.price']['format']);
    }

    #[Test]
    public function order_accepts_string(): void
    {
        $sort = (new FieldSort('price'))->order('desc');

        $this->assertSame(['price' => 'desc'], $sort->toArray());
    }
}
