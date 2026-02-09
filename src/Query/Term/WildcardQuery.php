<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasCaseInsensitive;
use Jackardios\EsScoutDriver\Query\Concerns\HasRewrite;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class WildcardQuery implements QueryInterface
{
    use HasBoost;
    use HasCaseInsensitive;
    use HasRewrite;

    private ?string $wildcard = null;

    public function __construct(
        private string $field,
        private string $value,
    ) {}

    public function wildcard(string $wildcard): self
    {
        $this->wildcard = $wildcard;
        return $this;
    }

    public function toArray(): array
    {
        $params = ['value' => $this->value];

        if ($this->wildcard !== null) {
            $params['wildcard'] = $this->wildcard;
        }

        $this->applyBoost($params);
        $this->applyRewrite($params);
        $this->applyCaseInsensitive($params);

        return ['wildcard' => [$this->field => $params]];
    }
}
