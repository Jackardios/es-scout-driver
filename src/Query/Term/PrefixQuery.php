<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Query\Concerns\HasCaseInsensitive;
use Jackardios\EsScoutDriver\Query\Concerns\HasRewrite;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class PrefixQuery implements QueryInterface
{
    use HasCaseInsensitive;
    use HasRewrite;

    public function __construct(
        private string $field,
        private string $value,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['value' => $this->value];

        $this->applyRewrite($params);
        $this->applyCaseInsensitive($params);

        return ['prefix' => [$this->field => $params]];
    }
}
