<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Specialized\MatchNoneQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MatchNoneQueryTest extends TestCase
{
    #[Test]
    public function it_builds_match_none_query(): void
    {
        $query = new MatchNoneQuery();

        $result = $query->toArray();
        $this->assertArrayHasKey('match_none', $result);
        $this->assertInstanceOf(\stdClass::class, $result['match_none']);
        $this->assertSame('{}', json_encode($result['match_none']));
    }
}
