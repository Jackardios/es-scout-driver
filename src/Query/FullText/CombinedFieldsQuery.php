<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\FullText;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasAutoGenerateSynonymsPhraseQuery;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasMinimumShouldMatch;
use Jackardios\EsScoutDriver\Query\Concerns\HasOperator;
use Jackardios\EsScoutDriver\Query\Concerns\HasZeroTermsQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class CombinedFieldsQuery implements QueryInterface
{
    use HasAutoGenerateSynonymsPhraseQuery;
    use HasBoost;
    use HasMinimumShouldMatch;
    use HasOperator;
    use HasZeroTermsQuery;

    /** @param array<int, string> $fields */
    public function __construct(
        private array $fields,
        private string $query,
    ) {
        if ($fields === []) {
            throw new InvalidQueryException('CombinedFieldsQuery requires at least one field');
        }
    }

    public function toArray(): array
    {
        $params = [
            'fields' => $this->fields,
            'query' => $this->query,
        ];

        $this->applyOperator($params);
        $this->applyMinimumShouldMatch($params);
        $this->applyBoost($params);
        $this->applyZeroTermsQuery($params);
        $this->applyAutoGenerateSynonymsPhraseQuery($params);

        return ['combined_fields' => $params];
    }
}
