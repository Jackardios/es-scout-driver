<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Query\Concerns\HasRewrite;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HasRewriteTest extends TestCase
{
    #[Test]
    public function rewrite_sets_value(): void
    {
        $query = $this->createQueryWithTrait();
        $result = $query->rewrite('constant_score');

        $this->assertSame($query, $result);
    }

    #[Test]
    public function apply_rewrite_adds_to_params(): void
    {
        $query = $this->createQueryWithTrait();
        $query->rewrite('constant_score_boolean');

        $params = [];
        $query->callApplyRewrite($params);

        $this->assertArrayHasKey('rewrite', $params);
        $this->assertSame('constant_score_boolean', $params['rewrite']);
    }

    #[Test]
    public function apply_rewrite_does_not_add_when_null(): void
    {
        $query = $this->createQueryWithTrait();

        $params = [];
        $query->callApplyRewrite($params);

        $this->assertArrayNotHasKey('rewrite', $params);
    }

    #[Test]
    public function rewrite_accepts_scoring_boolean(): void
    {
        $query = $this->createQueryWithTrait();
        $query->rewrite('scoring_boolean');

        $params = [];
        $query->callApplyRewrite($params);

        $this->assertSame('scoring_boolean', $params['rewrite']);
    }

    #[Test]
    public function rewrite_accepts_top_terms_boost(): void
    {
        $query = $this->createQueryWithTrait();
        $query->rewrite('top_terms_boost_N');

        $params = [];
        $query->callApplyRewrite($params);

        $this->assertSame('top_terms_boost_N', $params['rewrite']);
    }

    #[Test]
    public function rewrite_accepts_top_terms_blended_freqs(): void
    {
        $query = $this->createQueryWithTrait();
        $query->rewrite('top_terms_blended_freqs_N');

        $params = [];
        $query->callApplyRewrite($params);

        $this->assertSame('top_terms_blended_freqs_N', $params['rewrite']);
    }

    private function createQueryWithTrait(): object
    {
        return new class implements QueryInterface {
            use HasRewrite {
                applyRewrite as public callApplyRewrite;
            }

            public function toArray(): array
            {
                $params = [];
                $this->applyRewrite($params);
                return $params;
            }
        };
    }
}
