<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\FunctionScoreMode;

trait HasFunctionScoreMode
{
    private ?string $functionScoreMode = null;

    public function functionScoreMode(FunctionScoreMode|string $functionScoreMode): static
    {
        $this->functionScoreMode = $functionScoreMode instanceof FunctionScoreMode ? $functionScoreMode->value : $functionScoreMode;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyFunctionScoreMode(array &$params): void
    {
        if ($this->functionScoreMode !== null) {
            $params['score_mode'] = $this->functionScoreMode;
        }
    }
}
