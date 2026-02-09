<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\FunctionScoreMode;

trait HasFunctionScoreMode
{
    private ?string $scoreMode = null;

    public function scoreMode(FunctionScoreMode|string $scoreMode): self
    {
        $this->scoreMode = $scoreMode instanceof FunctionScoreMode ? $scoreMode->value : $scoreMode;
        return $this;
    }

    protected function applyScoreMode(array &$params): void
    {
        if ($this->scoreMode !== null) {
            $params['score_mode'] = $this->scoreMode;
        }
    }
}
