<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\RawQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RawQueryTest extends TestCase
{
    #[Test]
    public function it_returns_the_raw_array_as_is(): void
    {
        $query = new RawQuery(['match' => ['title' => 'test']]);

        $this->assertSame(['match' => ['title' => 'test']], $query->toArray());
    }

    #[Test]
    public function it_throws_for_empty_query_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('RawQuery requires a non-empty query array');

        new RawQuery([]);
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new RawQuery(['match_all' => new \stdClass()]);

        $this->assertSame($query, $query->query(['match_all' => []]));
    }

    #[Test]
    public function it_throws_for_empty_query_in_setter(): void
    {
        $query = new RawQuery(['match_all' => new \stdClass()]);

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('RawQuery requires a non-empty query array');

        $query->query([]);
    }
}
