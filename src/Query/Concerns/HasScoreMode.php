<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\ScoreMode;

trait HasScoreMode
{
    private ?string $scoreMode = null;

    public function scoreMode(ScoreMode|string $scoreMode): static
    {
        $this->scoreMode = $scoreMode instanceof ScoreMode ? $scoreMode->value : $scoreMode;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyScoreMode(array &$params): void
    {
        if ($this->scoreMode !== null) {
            $params['score_mode'] = $this->scoreMode;
        }
    }
}
