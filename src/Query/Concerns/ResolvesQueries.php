<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Closure;
use Jackardios\EsScoutDriver\Query\QueryInterface;

trait ResolvesQueries
{
    /**
     * Resolve a query to array format.
     *
     * @param QueryInterface|Closure():QueryInterface|Closure():array|array<string, mixed> $query
     * @return array<string, mixed>
     */
    protected function resolveQueryToArray(QueryInterface|Closure|array $query): array
    {
        if ($query instanceof Closure) {
            $query = $query();
        }

        if ($query instanceof QueryInterface) {
            return $query->toArray();
        }

        return $query;
    }

    /**
     * Resolve a query to QueryInterface or array (without converting to array).
     *
     * @param QueryInterface|Closure():QueryInterface|Closure():array|array<string, mixed> $query
     * @return QueryInterface|array<string, mixed>
     */
    protected function resolveQueryObject(QueryInterface|Closure|array $query): QueryInterface|array
    {
        if ($query instanceof Closure) {
            return $query();
        }

        return $query;
    }
}
