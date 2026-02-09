<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\FullText;

use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzer;
use Jackardios\EsScoutDriver\Query\Concerns\HasSlop;
use Jackardios\EsScoutDriver\Query\Concerns\HasZeroTermsQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class MatchPhrasePrefixQuery implements QueryInterface
{
    use HasAnalyzer;
    use HasSlop;
    use HasZeroTermsQuery;

    private ?int $maxExpansions = null;

    public function __construct(
        private string $field,
        private string $query,
    ) {}

    public function maxExpansions(int $maxExpansions): self
    {
        $this->maxExpansions = $maxExpansions;
        return $this;
    }

    public function toArray(): array
    {
        $params = ['query' => $this->query];

        $this->applyAnalyzer($params);

        if ($this->maxExpansions !== null) {
            $params['max_expansions'] = $this->maxExpansions;
        }

        $this->applySlop($params);
        $this->applyZeroTermsQuery($params);

        return ['match_phrase_prefix' => [$this->field => $params]];
    }
}
