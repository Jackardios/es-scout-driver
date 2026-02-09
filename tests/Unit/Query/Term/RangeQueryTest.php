<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Enums\RangeRelation;
use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Term\RangeQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RangeQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_range_query_with_gt(): void
    {
        $query = (new RangeQuery('age'))->gt(18);

        $this->assertSame([
            'range' => ['age' => ['gt' => 18]],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_range_query_with_gte_and_lte(): void
    {
        $query = (new RangeQuery('age'))->gte(18)->lte(65);

        $this->assertSame([
            'range' => ['age' => ['gte' => 18, 'lte' => 65]],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_range_query_with_lt(): void
    {
        $query = (new RangeQuery('price'))->lt(100.0);

        $this->assertSame([
            'range' => ['price' => ['lt' => 100.0]],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_range_query_with_all_options(): void
    {
        $query = (new RangeQuery('timestamp'))
            ->gte('2023-01-01')
            ->lte('2023-12-31')
            ->format('yyyy-MM-dd')
            ->relation('within')
            ->timeZone('+01:00')
            ->boost(2.0);

        $this->assertSame([
            'range' => ['timestamp' => [
                'gte' => '2023-01-01',
                'lte' => '2023-12-31',
                'format' => 'yyyy-MM-dd',
                'relation' => 'within',
                'time_zone' => '+01:00',
                'boost' => 2.0,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new RangeQuery('age');

        $this->assertSame($query, $query->gt(10));
        $this->assertSame($query, $query->gte(10));
        $this->assertSame($query, $query->lt(100));
        $this->assertSame($query, $query->lte(100));
        $this->assertSame($query, $query->format('yyyy-MM-dd'));
        $this->assertSame($query, $query->relation('within'));
        $this->assertSame($query, $query->timeZone('+00:00'));
        $this->assertSame($query, $query->boost(1.5));
    }

    #[Test]
    public function it_throws_on_no_bounds(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('RangeQuery requires at least one bound');

        $query = new RangeQuery('age');
        $query->toArray();
    }

    #[Test]
    public function it_accepts_range_relation_enum(): void
    {
        $query = (new RangeQuery('timestamp'))
            ->gte('2023-01-01')
            ->lte('2023-12-31')
            ->relation(RangeRelation::Within);

        $result = $query->toArray();
        $this->assertSame('within', $result['range']['timestamp']['relation']);
    }
}
