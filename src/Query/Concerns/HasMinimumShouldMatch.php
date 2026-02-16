<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasMinimumShouldMatch
{
    private string|int|null $minimumShouldMatch = null;

    public function minimumShouldMatch(string|int $minimumShouldMatch): static
    {
        $this->minimumShouldMatch = $minimumShouldMatch;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyMinimumShouldMatch(array &$params): void
    {
        if ($this->minimumShouldMatch !== null) {
            $params['minimum_should_match'] = $this->minimumShouldMatch;
        }
    }
}
