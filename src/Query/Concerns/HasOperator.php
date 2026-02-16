<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\Operator;

trait HasOperator
{
    private ?string $operator = null;

    public function operator(Operator|string $operator): static
    {
        $this->operator = $operator instanceof Operator ? $operator->value : $operator;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyOperator(array &$params): void
    {
        if ($this->operator !== null) {
            $params['operator'] = $this->operator;
        }
    }
}
