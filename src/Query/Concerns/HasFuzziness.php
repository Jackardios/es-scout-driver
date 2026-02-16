<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasFuzziness
{
    private string|int|null $fuzziness = null;
    private ?int $maxExpansions = null;
    private ?int $prefixLength = null;
    private ?bool $fuzzyTranspositions = null;
    private ?string $fuzzyRewrite = null;

    public function fuzziness(string|int $fuzziness): static
    {
        $this->fuzziness = $fuzziness;
        return $this;
    }

    public function maxExpansions(int $maxExpansions): static
    {
        $this->maxExpansions = $maxExpansions;
        return $this;
    }

    public function prefixLength(int $prefixLength): static
    {
        $this->prefixLength = $prefixLength;
        return $this;
    }

    public function fuzzyTranspositions(bool $fuzzyTranspositions = true): static
    {
        $this->fuzzyTranspositions = $fuzzyTranspositions;
        return $this;
    }

    public function fuzzyRewrite(string $fuzzyRewrite): static
    {
        $this->fuzzyRewrite = $fuzzyRewrite;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyFuzziness(array &$params): void
    {
        if ($this->fuzziness !== null) {
            $params['fuzziness'] = $this->fuzziness;
        }

        if ($this->maxExpansions !== null) {
            $params['max_expansions'] = $this->maxExpansions;
        }

        if ($this->prefixLength !== null) {
            $params['prefix_length'] = $this->prefixLength;
        }

        if ($this->fuzzyTranspositions !== null) {
            $params['fuzzy_transpositions'] = $this->fuzzyTranspositions;
        }

        if ($this->fuzzyRewrite !== null) {
            $params['fuzzy_rewrite'] = $this->fuzzyRewrite;
        }
    }
}
