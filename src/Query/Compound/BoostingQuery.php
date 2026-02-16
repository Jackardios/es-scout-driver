<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Compound;

use Jackardios\EsScoutDriver\Query\QueryInterface;

final class BoostingQuery implements QueryInterface
{
    private ?float $negativeBoost = null;

    public function __construct(
        private QueryInterface|array $positive,
        private QueryInterface|array $negative,
    ) {}

    public function negativeBoost(float $negativeBoost): self
    {
        $this->negativeBoost = $negativeBoost;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'positive' => $this->positive instanceof QueryInterface
                ? $this->positive->toArray()
                : $this->positive,
            'negative' => $this->negative instanceof QueryInterface
                ? $this->negative->toArray()
                : $this->negative,
        ];

        if ($this->negativeBoost !== null) {
            $params['negative_boost'] = $this->negativeBoost;
        }

        return ['boosting' => $params];
    }

    public function __clone(): void
    {
        if ($this->positive instanceof QueryInterface) {
            $this->positive = clone $this->positive;
        }
        if ($this->negative instanceof QueryInterface) {
            $this->negative = clone $this->negative;
        }
    }
}
