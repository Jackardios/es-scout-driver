<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Query\Concerns\HasTieBreaker;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasTieBreakerTest extends TestCase
{
    #[Test]
    public function tie_breaker_sets_value(): void
    {
        $query = $this->createQueryWithTrait();
        $result = $query->tieBreaker(0.3);

        $this->assertSame($query, $result);
    }

    #[Test]
    public function apply_tie_breaker_adds_to_params(): void
    {
        $query = $this->createQueryWithTrait();
        $query->tieBreaker(0.5);

        $params = [];
        $query->callApplyTieBreaker($params);

        $this->assertArrayHasKey('tie_breaker', $params);
        $this->assertSame(0.5, $params['tie_breaker']);
    }

    #[Test]
    public function apply_tie_breaker_does_not_add_when_null(): void
    {
        $query = $this->createQueryWithTrait();

        $params = [];
        $query->callApplyTieBreaker($params);

        $this->assertArrayNotHasKey('tie_breaker', $params);
    }

    #[Test]
    public function tie_breaker_accepts_zero(): void
    {
        $query = $this->createQueryWithTrait();
        $query->tieBreaker(0.0);

        $params = [];
        $query->callApplyTieBreaker($params);

        $this->assertSame(0.0, $params['tie_breaker']);
    }

    #[Test]
    public function tie_breaker_accepts_one(): void
    {
        $query = $this->createQueryWithTrait();
        $query->tieBreaker(1.0);

        $params = [];
        $query->callApplyTieBreaker($params);

        $this->assertSame(1.0, $params['tie_breaker']);
    }

    private function createQueryWithTrait(): object
    {
        return new class implements QueryInterface {
            use HasTieBreaker {
                applyTieBreaker as public callApplyTieBreaker;
            }

            public function toArray(): array
            {
                $params = [];
                $this->applyTieBreaker($params);
                return $params;
            }
        };
    }
}
