<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Query\Concerns\HasSlop;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasSlopTest extends TestCase
{
    #[Test]
    public function slop_sets_value(): void
    {
        $query = $this->createQueryWithTrait();
        $result = $query->slop(2);

        $this->assertSame($query, $result);
    }

    #[Test]
    public function apply_slop_adds_to_params(): void
    {
        $query = $this->createQueryWithTrait();
        $query->slop(3);

        $params = [];
        $query->callApplySlop($params);

        $this->assertArrayHasKey('slop', $params);
        $this->assertSame(3, $params['slop']);
    }

    #[Test]
    public function apply_slop_does_not_add_when_null(): void
    {
        $query = $this->createQueryWithTrait();

        $params = [];
        $query->callApplySlop($params);

        $this->assertArrayNotHasKey('slop', $params);
    }

    #[Test]
    public function slop_accepts_zero(): void
    {
        $query = $this->createQueryWithTrait();
        $query->slop(0);

        $params = [];
        $query->callApplySlop($params);

        $this->assertSame(0, $params['slop']);
    }

    #[Test]
    public function slop_accepts_large_value(): void
    {
        $query = $this->createQueryWithTrait();
        $query->slop(100);

        $params = [];
        $query->callApplySlop($params);

        $this->assertSame(100, $params['slop']);
    }

    private function createQueryWithTrait(): object
    {
        return new class implements QueryInterface {
            use HasSlop {
                applySlop as public callApplySlop;
            }

            public function toArray(): array
            {
                $params = [];
                $this->applySlop($params);
                return $params;
            }
        };
    }
}
