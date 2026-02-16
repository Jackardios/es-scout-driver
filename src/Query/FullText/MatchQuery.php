<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\FullText;

use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzer;
use Jackardios\EsScoutDriver\Query\Concerns\HasAutoGenerateSynonymsPhraseQuery;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasFuzziness;
use Jackardios\EsScoutDriver\Query\Concerns\HasLenient;
use Jackardios\EsScoutDriver\Query\Concerns\HasMinimumShouldMatch;
use Jackardios\EsScoutDriver\Query\Concerns\HasOperator;
use Jackardios\EsScoutDriver\Query\Concerns\HasZeroTermsQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class MatchQuery implements QueryInterface
{
    use HasAnalyzer;
    use HasAutoGenerateSynonymsPhraseQuery;
    use HasBoost;
    use HasFuzziness;
    use HasLenient;
    use HasMinimumShouldMatch;
    use HasOperator;
    use HasZeroTermsQuery;

    public function __construct(
        private string $field,
        private string|int|float|bool $query,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['query' => $this->query];

        $this->applyAnalyzer($params);
        $this->applyFuzziness($params);
        $this->applyOperator($params);
        $this->applyMinimumShouldMatch($params);
        $this->applyBoost($params);
        $this->applyLenient($params);
        $this->applyZeroTermsQuery($params);
        $this->applyAutoGenerateSynonymsPhraseQuery($params);

        return ['match' => [$this->field => $params]];
    }
}
