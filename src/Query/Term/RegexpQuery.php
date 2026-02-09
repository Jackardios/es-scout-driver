<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasCaseInsensitive;
use Jackardios\EsScoutDriver\Query\Concerns\HasRewrite;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class RegexpQuery implements QueryInterface
{
    use HasBoost;
    use HasCaseInsensitive;
    use HasRewrite;

    private ?string $flags = null;
    private ?int $maxDeterminizedStates = null;

    public function __construct(
        private string $field,
        private string $value,
    ) {}

    public function flags(string $flags): self
    {
        $this->flags = $flags;
        return $this;
    }

    public function maxDeterminizedStates(int $maxDeterminizedStates): self
    {
        $this->maxDeterminizedStates = $maxDeterminizedStates;
        return $this;
    }

    public function toArray(): array
    {
        $params = ['value' => $this->value];

        if ($this->flags !== null) {
            $params['flags'] = $this->flags;
        }

        if ($this->maxDeterminizedStates !== null) {
            $params['max_determinized_states'] = $this->maxDeterminizedStates;
        }

        $this->applyRewrite($params);
        $this->applyCaseInsensitive($params);
        $this->applyBoost($params);

        return ['regexp' => [$this->field => $params]];
    }
}
