<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Query\Concerns\HasCaseInsensitive;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasCaseInsensitiveTest extends TestCase
{
    #[Test]
    public function case_insensitive_sets_true_by_default(): void
    {
        $query = $this->createQueryWithTrait();
        $result = $query->caseInsensitive();

        $this->assertSame($query, $result);

        $params = [];
        $query->callApplyCaseInsensitive($params);

        $this->assertTrue($params['case_insensitive']);
    }

    #[Test]
    public function case_insensitive_sets_explicit_true(): void
    {
        $query = $this->createQueryWithTrait();
        $query->caseInsensitive(true);

        $params = [];
        $query->callApplyCaseInsensitive($params);

        $this->assertTrue($params['case_insensitive']);
    }

    #[Test]
    public function case_insensitive_sets_false(): void
    {
        $query = $this->createQueryWithTrait();
        $query->caseInsensitive(false);

        $params = [];
        $query->callApplyCaseInsensitive($params);

        $this->assertFalse($params['case_insensitive']);
    }

    #[Test]
    public function apply_case_insensitive_does_not_add_when_null(): void
    {
        $query = $this->createQueryWithTrait();

        $params = [];
        $query->callApplyCaseInsensitive($params);

        $this->assertArrayNotHasKey('case_insensitive', $params);
    }

    private function createQueryWithTrait(): object
    {
        return new class implements QueryInterface {
            use HasCaseInsensitive {
                applyCaseInsensitive as public callApplyCaseInsensitive;
            }

            public function toArray(): array
            {
                $params = [];
                $this->applyCaseInsensitive($params);
                return $params;
            }
        };
    }
}
