<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\Operator;

trait HasDefaultOperator
{
    private ?string $defaultOperator = null;

    public function defaultOperator(Operator|string $defaultOperator): static
    {
        $this->defaultOperator = $defaultOperator instanceof Operator ? $defaultOperator->value : $defaultOperator;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyDefaultOperator(array &$params): void
    {
        if ($this->defaultOperator !== null) {
            $params['default_operator'] = $this->defaultOperator;
        }
    }
}
