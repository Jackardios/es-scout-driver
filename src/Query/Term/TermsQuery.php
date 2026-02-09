<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class TermsQuery implements QueryInterface
{
    use HasBoost;

    /** @param array<int, string|int|float|bool> $values */
    public function __construct(
        private string $field,
        private array $values,
    ) {}

    public function toArray(): array
    {
        if ($this->values === []) {
            throw new InvalidQueryException('TermsQuery requires at least one value');
        }

        $query = [$this->field => $this->values];

        $this->applyBoost($query);

        return ['terms' => $query];
    }
}
