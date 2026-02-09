<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\ScoreMode;
use Jackardios\EsScoutDriver\Query\Concerns\HasScoreMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasScoreModeTest extends TestCase
{
    private function createTestClass(): object
    {
        return new class {
            use HasScoreMode;

            public function buildParams(): array
            {
                $params = [];
                $this->applyScoreMode($params);
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
    public function it_applies_score_mode_with_string_value(): void
    {
        $obj = $this->createTestClass();
        $obj->scoreMode('avg');

        $this->assertSame(['score_mode' => 'avg'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_score_mode_with_enum_avg(): void
    {
        $obj = $this->createTestClass();
        $obj->scoreMode(ScoreMode::Avg);

        $this->assertSame(['score_mode' => 'avg'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_score_mode_with_enum_max(): void
    {
        $obj = $this->createTestClass();
        $obj->scoreMode(ScoreMode::Max);

        $this->assertSame(['score_mode' => 'max'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_score_mode_with_enum_min(): void
    {
        $obj = $this->createTestClass();
        $obj->scoreMode(ScoreMode::Min);

        $this->assertSame(['score_mode' => 'min'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_score_mode_with_enum_sum(): void
    {
        $obj = $this->createTestClass();
        $obj->scoreMode(ScoreMode::Sum);

        $this->assertSame(['score_mode' => 'sum'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_score_mode_with_enum_none(): void
    {
        $obj = $this->createTestClass();
        $obj->scoreMode(ScoreMode::None);

        $this->assertSame(['score_mode' => 'none'], $obj->buildParams());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $obj = $this->createTestClass();

        $this->assertSame($obj, $obj->scoreMode('avg'));
        $this->assertSame($obj, $obj->scoreMode(ScoreMode::Max));
    }
}
