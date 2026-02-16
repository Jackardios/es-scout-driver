<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\FullText;

use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzer;
use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzeWildcard;
use Jackardios\EsScoutDriver\Query\Concerns\HasAutoGenerateSynonymsPhraseQuery;
use Jackardios\EsScoutDriver\Query\Concerns\HasDefaultOperator;
use Jackardios\EsScoutDriver\Query\Concerns\HasLenient;
use Jackardios\EsScoutDriver\Query\Concerns\HasMinimumShouldMatch;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class SimpleQueryStringQuery implements QueryInterface
{
    use HasAnalyzer;
    use HasAnalyzeWildcard;
    use HasAutoGenerateSynonymsPhraseQuery;
    use HasDefaultOperator;
    use HasLenient;
    use HasMinimumShouldMatch;

    /** @var array<int, string>|null */
    private ?array $fields = null;
    private ?string $flags = null;
    private ?int $fuzzyPrefixLength = null;
    private ?int $fuzzyMaxExpansions = null;
    private ?bool $fuzzyTranspositions = null;
    private ?string $quoteFieldSuffix = null;

    public function __construct(
        private string $query,
    ) {}

    /** @param array<int, string> $fields */
    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function flags(string $flags): self
    {
        $this->flags = $flags;
        return $this;
    }

    public function fuzzyPrefixLength(int $fuzzyPrefixLength): self
    {
        $this->fuzzyPrefixLength = $fuzzyPrefixLength;
        return $this;
    }

    public function fuzzyMaxExpansions(int $fuzzyMaxExpansions): self
    {
        $this->fuzzyMaxExpansions = $fuzzyMaxExpansions;
        return $this;
    }

    public function fuzzyTranspositions(bool $fuzzyTranspositions = true): self
    {
        $this->fuzzyTranspositions = $fuzzyTranspositions;
        return $this;
    }

    public function quoteFieldSuffix(string $quoteFieldSuffix): self
    {
        $this->quoteFieldSuffix = $quoteFieldSuffix;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['query' => $this->query];

        if ($this->fields !== null) {
            $params['fields'] = $this->fields;
        }

        $this->applyDefaultOperator($params);
        $this->applyAnalyzer($params);
        $this->applyLenient($params);
        $this->applyMinimumShouldMatch($params);
        $this->applyAnalyzeWildcard($params);
        $this->applyAutoGenerateSynonymsPhraseQuery($params);

        if ($this->flags !== null) {
            $params['flags'] = $this->flags;
        }

        if ($this->fuzzyPrefixLength !== null) {
            $params['fuzzy_prefix_length'] = $this->fuzzyPrefixLength;
        }

        if ($this->fuzzyMaxExpansions !== null) {
            $params['fuzzy_max_expansions'] = $this->fuzzyMaxExpansions;
        }

        if ($this->fuzzyTranspositions !== null) {
            $params['fuzzy_transpositions'] = $this->fuzzyTranspositions;
        }

        if ($this->quoteFieldSuffix !== null) {
            $params['quote_field_suffix'] = $this->quoteFieldSuffix;
        }

        return ['simple_query_string' => $params];
    }
}
