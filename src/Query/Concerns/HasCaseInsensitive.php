<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasCaseInsensitive
{
    private ?bool $caseInsensitive = null;

    public function caseInsensitive(bool $caseInsensitive = true): self
    {
        $this->caseInsensitive = $caseInsensitive;
        return $this;
    }

    protected function applyCaseInsensitive(array &$params): void
    {
        if ($this->caseInsensitive !== null) {
            $params['case_insensitive'] = $this->caseInsensitive;
        }
    }
}
