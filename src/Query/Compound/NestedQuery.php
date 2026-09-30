<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Compound;

use Closure;
use Jackardios\EsScoutDriver\Query\Concerns\HasIgnoreUnmapped;
use Jackardios\EsScoutDriver\Query\Concerns\HasInnerHits;
use Jackardios\EsScoutDriver\Query\Concerns\HasScoreMode;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\SubQuery;

final class NestedQuery implements QueryInterface
{
    use HasIgnoreUnmapped;
    use HasInnerHits;
    use HasScoreMode;

    private QueryInterface|array $query;

    public function __construct(
        private string $path,
        QueryInterface|Closure|array $query,
    ) {
        $this->query = SubQuery::resolve($query);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'path' => $this->path,
            'query' => SubQuery::toArray($this->query),
        ];

        $this->applyScoreMode($params);
        $this->applyIgnoreUnmapped($params);
        $this->applyInnerHits($params);

        return ['nested' => $params];
    }

    public function __clone(): void
    {
        if ($this->query instanceof QueryInterface) {
            $this->query = clone $this->query;
        }
    }
}
