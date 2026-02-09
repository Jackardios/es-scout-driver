<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Query\Concerns\HasFuzziness;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasFuzzinessTest extends TestCase
{
    private function createTestClass(): object
    {
        return new class {
            use HasFuzziness;

            public function buildParams(): array
            {
                $params = [];
                $this->applyFuzziness($params);
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
    public function it_applies_fuzziness_with_string_value(): void
    {
        $obj = $this->createTestClass();
        $obj->fuzziness('AUTO');

        $this->assertSame(['fuzziness' => 'AUTO'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_fuzziness_with_integer_value(): void
    {
        $obj = $this->createTestClass();
        $obj->fuzziness(2);

        $this->assertSame(['fuzziness' => 2], $obj->buildParams());
    }

    #[Test]
    public function it_applies_max_expansions(): void
    {
        $obj = $this->createTestClass();
        $obj->maxExpansions(50);

        $this->assertSame(['max_expansions' => 50], $obj->buildParams());
    }

    #[Test]
    public function it_applies_prefix_length(): void
    {
        $obj = $this->createTestClass();
        $obj->prefixLength(3);

        $this->assertSame(['prefix_length' => 3], $obj->buildParams());
    }

    #[Test]
    public function it_applies_fuzzy_transpositions_true(): void
    {
        $obj = $this->createTestClass();
        $obj->fuzzyTranspositions(true);

        $this->assertSame(['fuzzy_transpositions' => true], $obj->buildParams());
    }

    #[Test]
    public function it_applies_fuzzy_transpositions_false(): void
    {
        $obj = $this->createTestClass();
        $obj->fuzzyTranspositions(false);

        $this->assertSame(['fuzzy_transpositions' => false], $obj->buildParams());
    }

    #[Test]
    public function it_applies_fuzzy_transpositions_default_to_true(): void
    {
        $obj = $this->createTestClass();
        $obj->fuzzyTranspositions();

        $this->assertSame(['fuzzy_transpositions' => true], $obj->buildParams());
    }

    #[Test]
    public function it_applies_fuzzy_rewrite(): void
    {
        $obj = $this->createTestClass();
        $obj->fuzzyRewrite('constant_score');

        $this->assertSame(['fuzzy_rewrite' => 'constant_score'], $obj->buildParams());
    }

    #[Test]
    public function it_applies_all_fuzziness_params(): void
    {
        $obj = $this->createTestClass();
        $obj->fuzziness('AUTO')
            ->maxExpansions(100)
            ->prefixLength(2)
            ->fuzzyTranspositions(false)
            ->fuzzyRewrite('top_terms_boost_N');

        $this->assertSame([
            'fuzziness' => 'AUTO',
            'max_expansions' => 100,
            'prefix_length' => 2,
            'fuzzy_transpositions' => false,
            'fuzzy_rewrite' => 'top_terms_boost_N',
        ], $obj->buildParams());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $obj = $this->createTestClass();

        $this->assertSame($obj, $obj->fuzziness('AUTO'));
        $this->assertSame($obj, $obj->maxExpansions(50));
        $this->assertSame($obj, $obj->prefixLength(2));
        $this->assertSame($obj, $obj->fuzzyTranspositions());
        $this->assertSame($obj, $obj->fuzzyRewrite('constant_score'));
    }
}
