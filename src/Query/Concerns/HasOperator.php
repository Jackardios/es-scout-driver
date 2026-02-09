<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\Operator;

trait HasOperator
{
    private ?string $operator = null;

    public function operator(Operator|string $operator): self
    {
        $this->operator = $operator instanceof Operator ? $operator->value : $operator;
        return $this;
    }

    protected function applyOperator(array &$params): void
    {
        if ($this->operator !== null) {
            $params['operator'] = $this->operator;
        }
    }
}
