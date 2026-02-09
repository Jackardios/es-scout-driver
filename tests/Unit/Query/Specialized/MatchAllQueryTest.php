<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MatchAllQueryTest extends TestCase
{
    #[Test]
    public function it_builds_match_all_query_without_boost(): void
    {
        $query = new MatchAllQuery();

        $result = $query->toArray();
        $this->assertArrayHasKey('match_all', $result);
        $this->assertInstanceOf(\stdClass::class, $result['match_all']);
        $this->assertSame('{}', json_encode($result['match_all']));
    }

    #[Test]
    public function it_builds_match_all_query_with_boost(): void
    {
        $query = (new MatchAllQuery())->boost(1.5);

        $this->assertSame([
            'match_all' => ['boost' => 1.5],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new MatchAllQuery();

        $this->assertSame($query, $query->boost(1.5));
    }
}
