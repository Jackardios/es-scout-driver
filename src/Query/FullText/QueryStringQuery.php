<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\FullText;

use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzer;
use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzeWildcard;
use Jackardios\EsScoutDriver\Query\Concerns\HasAutoGenerateSynonymsPhraseQuery;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasDefaultOperator;
use Jackardios\EsScoutDriver\Query\Concerns\HasFuzziness;
use Jackardios\EsScoutDriver\Query\Concerns\HasLenient;
use Jackardios\EsScoutDriver\Query\Concerns\HasMinimumShouldMatch;
use Jackardios\EsScoutDriver\Query\Concerns\HasRewrite;
use Jackardios\EsScoutDriver\Query\Concerns\HasTieBreaker;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class QueryStringQuery implements QueryInterface
{
    use HasAnalyzer;
    use HasAnalyzeWildcard;
    use HasAutoGenerateSynonymsPhraseQuery;
    use HasBoost;
    use HasDefaultOperator;
    use HasFuzziness;
    use HasLenient;
    use HasMinimumShouldMatch;
    use HasRewrite;
    use HasTieBreaker;

    private ?string $defaultField = null;
    /** @var array<int, string>|null */
    private ?array $fields = null;
    private ?bool $allowLeadingWildcard = null;
    private ?int $phraseSlop = null;
    private ?string $quoteFieldSuffix = null;
    private ?string $quoteAnalyzer = null;
    private ?bool $enablePositionIncrements = null;
    private ?bool $escape = null;

    public function __construct(
        private string $query,
    ) {}

    public function defaultField(string $defaultField): self
    {
        $this->defaultField = $defaultField;
        return $this;
    }

    /** @param array<int, string> $fields */
    public function fields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function allowLeadingWildcard(bool $allowLeadingWildcard = true): self
    {
        $this->allowLeadingWildcard = $allowLeadingWildcard;
        return $this;
    }

    public function phraseSlop(int $phraseSlop): self
    {
        $this->phraseSlop = $phraseSlop;
        return $this;
    }

    public function quoteFieldSuffix(string $quoteFieldSuffix): self
    {
        $this->quoteFieldSuffix = $quoteFieldSuffix;
        return $this;
    }

    public function quoteAnalyzer(string $quoteAnalyzer): self
    {
        $this->quoteAnalyzer = $quoteAnalyzer;
        return $this;
    }

    public function enablePositionIncrements(bool $enablePositionIncrements = true): self
    {
        $this->enablePositionIncrements = $enablePositionIncrements;
        return $this;
    }

    public function escape(bool $escape = true): self
    {
        $this->escape = $escape;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['query' => $this->query];

        if ($this->defaultField !== null) {
            $params['default_field'] = $this->defaultField;
        }

        if ($this->fields !== null) {
            $params['fields'] = $this->fields;
        }

        $this->applyAnalyzer($params);
        $this->applyDefaultOperator($params);
        $this->applyBoost($params);
        $this->applyMinimumShouldMatch($params);
        $this->applyLenient($params);
        $this->applyAnalyzeWildcard($params);

        if ($this->allowLeadingWildcard !== null) {
            $params['allow_leading_wildcard'] = $this->allowLeadingWildcard;
        }

        $this->applyAutoGenerateSynonymsPhraseQuery($params);
        $this->applyFuzziness($params);
        $this->applyRewrite($params);
        $this->applyTieBreaker($params);

        if ($this->phraseSlop !== null) {
            $params['phrase_slop'] = $this->phraseSlop;
        }

        if ($this->quoteFieldSuffix !== null) {
            $params['quote_field_suffix'] = $this->quoteFieldSuffix;
        }

        if ($this->quoteAnalyzer !== null) {
            $params['quote_analyzer'] = $this->quoteAnalyzer;
        }

        if ($this->enablePositionIncrements !== null) {
            $params['enable_position_increments'] = $this->enablePositionIncrements;
        }

        if ($this->escape !== null) {
            $params['escape'] = $this->escape;
        }

        return ['query_string' => $params];
    }
}
