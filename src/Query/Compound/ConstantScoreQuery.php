<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Compound;

use Closure;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class ConstantScoreQuery implements QueryInterface
{
    use HasBoost;

    private QueryInterface|array $filter;

    /** @param QueryInterface|Closure|array $filter */
    public function __construct(QueryInterface|Closure|array $filter)
    {
        $this->filter = $filter instanceof Closure ? $filter() : $filter;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'filter' => $this->filter instanceof QueryInterface ? $this->filter->toArray() : $this->filter,
        ];

        $this->applyBoost($params);

        return ['constant_score' => $params];
    }

    public function __clone(): void
    {
        if ($this->filter instanceof QueryInterface) {
            $this->filter = clone $this->filter;
        }
    }
}
