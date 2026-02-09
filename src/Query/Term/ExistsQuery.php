<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Query\QueryInterface;

final class ExistsQuery implements QueryInterface
{
    public function __construct(
        private string $field,
    ) {}

    public function toArray(): array
    {
        return ['exists' => ['field' => $this->field]];
    }
}
