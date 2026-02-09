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

    public function fuzzyTranspositions(bool $fuzzyTranspositions = true): self
    {
        $this->fuzzyTranspositions = $fuzzyTranspositions;
        return $this;
    }

    public function fuzzyRewrite(string $fuzzyRewrite): self
    {
        $this->fuzzyRewrite = $fuzzyRewrite;
        return $this;
    }

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
