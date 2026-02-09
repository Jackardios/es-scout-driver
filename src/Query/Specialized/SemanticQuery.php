<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * Semantic query for semantic text fields (ES 8.14+).
 *
 * Uses a natural language processing (NLP) model to convert the query
 * into a list of token-weight pairs, which are then used in a
 * weighted token query against the inference field.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-semantic-query.html
 */
final class SemanticQuery implements QueryInterface
{
    use HasBoost;

    public function __construct(
        private string $field,
        private string $query,
    ) {}

    public function toArray(): array
    {
        $params = [
            'field' => $this->field,
            'query' => $this->query,
        ];

        $this->applyBoost($params);

        return ['semantic' => $params];
    }
}
