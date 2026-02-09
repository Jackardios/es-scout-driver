<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\Operator;

trait HasDefaultOperator
{
    private ?string $defaultOperator = null;

    public function defaultOperator(Operator|string $defaultOperator): self
    {
        $this->defaultOperator = $defaultOperator instanceof Operator ? $defaultOperator->value : $defaultOperator;
        return $this;
    }

    protected function applyDefaultOperator(array &$params): void
    {
        if ($this->defaultOperator !== null) {
            $params['default_operator'] = $this->defaultOperator;
        }
    }
}
