<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasAnalyzer
{
    private ?string $analyzer = null;

    public function analyzer(string $analyzer): static
    {
        $this->analyzer = $analyzer;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyAnalyzer(array &$params): void
    {
        if ($this->analyzer !== null) {
            $params['analyzer'] = $this->analyzer;
        }
    }
}
