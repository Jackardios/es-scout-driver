<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasAnalyzeWildcard
{
    private ?bool $analyzeWildcard = null;

    public function analyzeWildcard(bool $analyzeWildcard = true): static
    {
        $this->analyzeWildcard = $analyzeWildcard;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyAnalyzeWildcard(array &$params): void
    {
        if ($this->analyzeWildcard !== null) {
            $params['analyze_wildcard'] = $this->analyzeWildcard;
        }
    }
}
