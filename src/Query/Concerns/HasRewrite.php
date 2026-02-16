<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasRewrite
{
    private ?string $rewrite = null;

    public function rewrite(string $rewrite): static
    {
        $this->rewrite = $rewrite;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyRewrite(array &$params): void
    {
        if ($this->rewrite !== null) {
            $params['rewrite'] = $this->rewrite;
        }
    }
}
