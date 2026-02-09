<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasSlop
{
    private ?int $slop = null;

    public function slop(int $slop): self
    {
        $this->slop = $slop;
        return $this;
    }

    protected function applySlop(array &$params): void
    {
        if ($this->slop !== null) {
            $params['slop'] = $this->slop;
        }
    }
}
