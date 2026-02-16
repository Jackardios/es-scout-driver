<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasIgnoreUnmapped
{
    private ?bool $ignoreUnmapped = null;

    public function ignoreUnmapped(bool $ignoreUnmapped = true): static
    {
        $this->ignoreUnmapped = $ignoreUnmapped;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyIgnoreUnmapped(array &$params): void
    {
        if ($this->ignoreUnmapped !== null) {
            $params['ignore_unmapped'] = $this->ignoreUnmapped;
        }
    }
}
