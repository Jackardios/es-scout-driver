<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class ExistsQuery implements QueryInterface
{
    use HasBoost;

    public function __construct(
        private string $field,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['field' => $this->field];

        $this->applyBoost($params);

        return ['exists' => $params];
    }
}
