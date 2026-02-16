<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasTieBreaker
{
    private ?float $tieBreaker = null;

    public function tieBreaker(float $tieBreaker): static
    {
        $this->tieBreaker = $tieBreaker;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyTieBreaker(array &$params): void
    {
        if ($this->tieBreaker !== null) {
            $params['tie_breaker'] = $this->tieBreaker;
        }
    }
}
