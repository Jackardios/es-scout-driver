<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\Operator;
use Jackardios\EsScoutDriver\Query\Concerns\HasOperator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasOperatorTest extends TestCase
{
    private function createTestClass(): object
    {
        return new class {
            use HasOperator;

            public function buildParams(): array
            {
                $params = [];
                $this->applyOperator($params);
                return $params;
            }
        };
    }

    #[Test]
    public function it_applies_no_params_by_default(): void
    {
        $obj = $this->createTestClass();

        $this->assertSame([], $obj->buildParams());
    }

    #[Test]
    public function it_applies_operator_with_string_value(): void
    {
        $obj = $this->createTestClass();
        $obj->operator('and');

        $this->assertSame(['operator' => 'and'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_operator_with_enum_and_value(): void
    {
        $obj = $this->createTestClass();
        $obj->operator(Operator::And);

        $this->assertSame(['operator' => 'and'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_operator_with_enum_or_value(): void
    {
        $obj = $this->createTestClass();
        $obj->operator(Operator::Or);

        $this->assertSame(['operator' => 'or'], $obj->buildParams());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $obj = $this->createTestClass();

        $this->assertSame($obj, $obj->operator('and'));
        $this->assertSame($obj, $obj->operator(Operator::Or));
    }
}
