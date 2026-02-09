<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\ZeroTermsQuery;

trait HasZeroTermsQuery
{
    private ?string $zeroTermsQuery = null;

    public function zeroTermsQuery(ZeroTermsQuery|string $zeroTermsQuery): self
    {
        $this->zeroTermsQuery = $zeroTermsQuery instanceof ZeroTermsQuery ? $zeroTermsQuery->value : $zeroTermsQuery;
        return $this;
    }

    protected function applyZeroTermsQuery(array &$params): void
    {
        if ($this->zeroTermsQuery !== null) {
            $params['zero_terms_query'] = $this->zeroTermsQuery;
        }
    }
}
