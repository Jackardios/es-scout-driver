<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasSlop
{
    private ?int $slop = null;

    public function slop(int $slop): static
    {
        $this->slop = $slop;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applySlop(array &$params): void
    {
        if ($this->slop !== null) {
            $params['slop'] = $this->slop;
        }
    }
}
