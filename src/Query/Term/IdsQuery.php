<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class IdsQuery implements QueryInterface
{
    /** @param array<int, string> $values */
    public function __construct(
        private array $values,
    ) {}

    public function toArray(): array
    {
        if ($this->values === []) {
            throw new InvalidQueryException('IdsQuery requires at least one value');
        }

        return ['ids' => ['values' => $this->values]];
    }
}
