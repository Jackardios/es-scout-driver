<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasCaseInsensitive;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class TermQuery implements QueryInterface
{
    use HasBoost;
    use HasCaseInsensitive;

    public function __construct(
        private string $field,
        private string|int|float|bool $value,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['value' => $this->value];

        $this->applyBoost($params);
        $this->applyCaseInsensitive($params);

        return ['term' => [$this->field => $params]];
    }
}
