<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasQueryStringFuzziness
{
    private ?int $fuzzyPrefixLength = null;
    private ?int $fuzzyMaxExpansions = null;
    private ?bool $fuzzyTranspositions = null;

    public function fuzzyPrefixLength(int $fuzzyPrefixLength): static
    {
        $this->fuzzyPrefixLength = $fuzzyPrefixLength;
        return $this;
    }

    public function fuzzyMaxExpansions(int $fuzzyMaxExpansions): static
    {
        $this->fuzzyMaxExpansions = $fuzzyMaxExpansions;
        return $this;
    }

    public function fuzzyTranspositions(bool $fuzzyTranspositions = true): static
    {
        $this->fuzzyTranspositions = $fuzzyTranspositions;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyQueryStringFuzziness(array &$params): void
    {
        if ($this->fuzzyPrefixLength !== null) {
            $params['fuzzy_prefix_length'] = $this->fuzzyPrefixLength;
        }

        if ($this->fuzzyMaxExpansions !== null) {
            $params['fuzzy_max_expansions'] = $this->fuzzyMaxExpansions;
        }

        if ($this->fuzzyTranspositions !== null) {
            $params['fuzzy_transpositions'] = $this->fuzzyTranspositions;
        }
    }
}
