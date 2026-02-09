<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasRewrite
{
    private ?string $rewrite = null;

    public function rewrite(string $rewrite): self
    {
        $this->rewrite = $rewrite;
        return $this;
    }

    protected function applyRewrite(array &$params): void
    {
        if ($this->rewrite !== null) {
            $params['rewrite'] = $this->rewrite;
        }
    }
}
