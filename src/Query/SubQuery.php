<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query;

use Closure;
use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;

/**
 * A query nested in another query: a QueryInterface, a raw array, or a closure returning either.
 *
 * @internal
 */
final class SubQuery
{
    /**
     * Call a closure, which takes no arguments and must return a QueryInterface or an array.
     *
     * @param QueryInterface|Closure|array<string, mixed> $query
     * @return QueryInterface|array<string, mixed>
     *
     * @throws InvalidQueryException when the closure returns something else
     */
    public static function resolve(QueryInterface|Closure|array $query): QueryInterface|array
    {
        if (!$query instanceof Closure) {
            return $query;
        }

        $resolved = $query();

        if ($resolved instanceof QueryInterface || is_array($resolved)) {
            return $resolved;
        }

        throw new InvalidQueryException(sprintf(
            'A query closure must return a %s or an array, %s returned',
            QueryInterface::class,
            get_debug_type($resolved),
        ));
    }

    /**
     * @param QueryInterface|array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function toArray(QueryInterface|array $query): array
    {
        return $query instanceof QueryInterface ? $query->toArray() : $query;
    }
}
