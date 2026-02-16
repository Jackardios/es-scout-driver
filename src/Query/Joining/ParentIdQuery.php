<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Joining;

use Jackardios\EsScoutDriver\Query\Concerns\HasIgnoreUnmapped;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * Returns child documents joined to a specific parent document.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-parent-id-query.html
 */
final class ParentIdQuery implements QueryInterface
{
    use HasIgnoreUnmapped;

    public function __construct(
        private string $type,
        private string $id,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'type' => $this->type,
            'id' => $this->id,
        ];

        $this->applyIgnoreUnmapped($params);

        return ['parent_id' => $params];
    }
}
