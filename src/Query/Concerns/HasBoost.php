<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasBoost
{
    private ?float $boost = null;

    public function boost(float $boost): self
    {
        $this->boost = $boost;
        return $this;
    }

    protected function applyBoost(array &$params): void
    {
        if ($this->boost !== null) {
            $params['boost'] = $this->boost;
        }
    }
}
