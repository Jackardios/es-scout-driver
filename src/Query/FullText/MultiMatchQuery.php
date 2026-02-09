<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\FullText;

use Jackardios\EsScoutDriver\Enums\MultiMatchType;
use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzer;
use Jackardios\EsScoutDriver\Query\Concerns\HasAutoGenerateSynonymsPhraseQuery;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasFuzziness;
use Jackardios\EsScoutDriver\Query\Concerns\HasLenient;
use Jackardios\EsScoutDriver\Query\Concerns\HasMinimumShouldMatch;
use Jackardios\EsScoutDriver\Query\Concerns\HasOperator;
use Jackardios\EsScoutDriver\Query\Concerns\HasTieBreaker;
use Jackardios\EsScoutDriver\Query\Concerns\HasZeroTermsQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class MultiMatchQuery implements QueryInterface
{
    use HasAnalyzer;
    use HasAutoGenerateSynonymsPhraseQuery;
    use HasBoost;
    use HasFuzziness;
    use HasLenient;
    use HasMinimumShouldMatch;
    use HasOperator;
    use HasTieBreaker;
    use HasZeroTermsQuery;

    private ?string $type = null;

    /** @param array<int, string> $fields */
    public function __construct(
        private array $fields,
        private string|int|float|bool $query,
    ) {
        if ($fields === []) {
            throw new InvalidQueryException('MultiMatchQuery requires at least one field');
        }
    }

    public function type(MultiMatchType|string $type): self
    {
        $this->type = $type instanceof MultiMatchType ? $type->value : $type;
        return $this;
    }

    public function toArray(): array
    {
        $params = [
            'fields' => $this->fields,
            'query' => $this->query,
        ];

        if ($this->type !== null) {
            $params['type'] = $this->type;
        }

        $this->applyAnalyzer($params);
        $this->applyOperator($params);
        $this->applyMinimumShouldMatch($params);
        $this->applyTieBreaker($params);
        $this->applyBoost($params);
        $this->applyFuzziness($params);
        $this->applyLenient($params);
        $this->applyZeroTermsQuery($params);
        $this->applyAutoGenerateSynonymsPhraseQuery($params);

        return ['multi_match' => $params];
    }
}
