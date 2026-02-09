<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Query\Concerns\HasIgnoreUnmapped;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasIgnoreUnmappedTest extends TestCase
{
    #[Test]
    public function ignore_unmapped_sets_true_by_default(): void
    {
        $query = $this->createQueryWithTrait();
        $result = $query->ignoreUnmapped();

        $this->assertSame($query, $result);

        $params = [];
        $query->callApplyIgnoreUnmapped($params);

        $this->assertTrue($params['ignore_unmapped']);
    }

    #[Test]
    public function ignore_unmapped_sets_explicit_true(): void
    {
        $query = $this->createQueryWithTrait();
        $query->ignoreUnmapped(true);

        $params = [];
        $query->callApplyIgnoreUnmapped($params);

        $this->assertTrue($params['ignore_unmapped']);
    }

    #[Test]
    public function ignore_unmapped_sets_false(): void
    {
        $query = $this->createQueryWithTrait();
        $query->ignoreUnmapped(false);

        $params = [];
        $query->callApplyIgnoreUnmapped($params);

        $this->assertFalse($params['ignore_unmapped']);
    }

    #[Test]
    public function apply_ignore_unmapped_does_not_add_when_null(): void
    {
        $query = $this->createQueryWithTrait();

        $params = [];
        $query->callApplyIgnoreUnmapped($params);

        $this->assertArrayNotHasKey('ignore_unmapped', $params);
    }

    private function createQueryWithTrait(): object
    {
        return new class implements QueryInterface {
            use HasIgnoreUnmapped {
                applyIgnoreUnmapped as public callApplyIgnoreUnmapped;
            }

            public function toArray(): array
            {
                $params = [];
                $this->applyIgnoreUnmapped($params);
                return $params;
            }
        };
    }
}
