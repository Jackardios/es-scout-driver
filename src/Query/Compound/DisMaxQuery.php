<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Compound;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasTieBreaker;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class DisMaxQuery implements QueryInterface
{
    use HasBoost;
    use HasTieBreaker;

    /** @var array<QueryInterface|array> */
    private array $queries = [];

    /** @param array<QueryInterface|array> $queries */
    public function __construct(array $queries = [])
    {
        $this->queries = $queries;
    }

    /** @param QueryInterface|array ...$queries */
    public function queries(QueryInterface|array ...$queries): self
    {
        $this->queries = array_values($queries);
        return $this;
    }

    public function add(QueryInterface|array $query): self
    {
        $this->queries[] = $query;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->queries === []) {
            throw new InvalidQueryException('DisMaxQuery requires at least one query');
        }

        $params = [
            'queries' => array_map(
                static fn(QueryInterface|array $q) => $q instanceof QueryInterface ? $q->toArray() : $q,
                $this->queries,
            ),
        ];

        $this->applyTieBreaker($params);
        $this->applyBoost($params);

        return ['dis_max' => $params];
    }

    public function __clone(): void
    {
        $this->queries = array_map(
            static fn(QueryInterface|array $q) => $q instanceof QueryInterface ? clone $q : $q,
            $this->queries,
        );
    }
}
