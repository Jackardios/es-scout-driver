<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasIgnoreUnmapped
{
    private ?bool $ignoreUnmapped = null;

    public function ignoreUnmapped(bool $ignoreUnmapped = true): self
    {
        $this->ignoreUnmapped = $ignoreUnmapped;
        return $this;
    }

    protected function applyIgnoreUnmapped(array &$params): void
    {
        if ($this->ignoreUnmapped !== null) {
            $params['ignore_unmapped'] = $this->ignoreUnmapped;
        }
    }
}
