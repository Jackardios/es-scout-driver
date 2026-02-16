<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\FullText;

use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzer;
use Jackardios\EsScoutDriver\Query\Concerns\HasSlop;
use Jackardios\EsScoutDriver\Query\Concerns\HasZeroTermsQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class MatchPhraseQuery implements QueryInterface
{
    use HasAnalyzer;
    use HasSlop;
    use HasZeroTermsQuery;

    public function __construct(
        private string $field,
        private string $query,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['query' => $this->query];

        $this->applyAnalyzer($params);
        $this->applySlop($params);
        $this->applyZeroTermsQuery($params);

        return ['match_phrase' => [$this->field => $params]];
    }
}
