<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Concerns;

trait HasMissing
{
    private string|int|float|bool|null $missing = null;

    public function missing(string|int|float|bool $value): static
    {
        $this->missing = $value;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyMissing(array &$params): void
    {
        if ($this->missing !== null) {
            $params['missing'] = $this->missing;
        }
    }
}
