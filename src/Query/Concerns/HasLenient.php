<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasLenient
{
    private ?bool $lenient = null;

    public function lenient(bool $lenient = true): self
    {
        $this->lenient = $lenient;
        return $this;
    }

    protected function applyLenient(array &$params): void
    {
        if ($this->lenient !== null) {
            $params['lenient'] = $this->lenient;
        }
    }
}
