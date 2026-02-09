<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query;

use Jackardios\EsScoutDriver\Query\RawQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RawQueryTest extends TestCase
{
    #[Test]
    public function it_returns_the_raw_array_as_is(): void
    {
        $query = (new RawQuery())->query(['match' => ['title' => 'test']]);

        $this->assertSame(['match' => ['title' => 'test']], $query->toArray());
    }

    #[Test]
    public function it_returns_empty_array_when_no_query_set(): void
    {
        $query = new RawQuery();

        $this->assertSame([], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new RawQuery();

        $this->assertSame($query, $query->query(['match_all' => []]));
    }
}
