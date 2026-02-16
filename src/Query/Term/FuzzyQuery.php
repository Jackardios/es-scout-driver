<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasRewrite;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class FuzzyQuery implements QueryInterface
{
    use HasBoost;
    use HasRewrite;

    private string|int|null $fuzziness = null;
    private ?int $maxExpansions = null;
    private ?int $prefixLength = null;
    private ?bool $transpositions = null;

    public function __construct(
        private string $field,
        private string $value,
    ) {}

    public function fuzziness(string|int $fuzziness): self
    {
        $this->fuzziness = $fuzziness;
        return $this;
    }

    public function maxExpansions(int $maxExpansions): self
    {
        $this->maxExpansions = $maxExpansions;
        return $this;
    }

    public function prefixLength(int $prefixLength): self
    {
        $this->prefixLength = $prefixLength;
        return $this;
    }

    public function transpositions(bool $transpositions = true): self
    {
        $this->transpositions = $transpositions;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['value' => $this->value];

        if ($this->fuzziness !== null) {
            $params['fuzziness'] = $this->fuzziness;
        }

        if ($this->maxExpansions !== null) {
            $params['max_expansions'] = $this->maxExpansions;
        }

        if ($this->prefixLength !== null) {
            $params['prefix_length'] = $this->prefixLength;
        }

        $this->applyRewrite($params);

        if ($this->transpositions !== null) {
            $params['transpositions'] = $this->transpositions;
        }

        $this->applyBoost($params);

        return ['fuzzy' => [$this->field => $params]];
    }
}
