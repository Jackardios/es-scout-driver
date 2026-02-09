<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Joining;

use Closure;
use Jackardios\EsScoutDriver\Query\Concerns\HasIgnoreUnmapped;
use Jackardios\EsScoutDriver\Query\Concerns\HasInnerHits;
use Jackardios\EsScoutDriver\Query\Concerns\HasScoreMode;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class HasChildQuery implements QueryInterface
{
    use HasIgnoreUnmapped;
    use HasInnerHits;
    use HasScoreMode;

    private QueryInterface|array $query;
    private ?int $minChildren = null;
    private ?int $maxChildren = null;

    /** @param QueryInterface|Closure|array $query */
    public function __construct(
        private string $type,
        QueryInterface|Closure|array $query,
    ) {
        $this->query = $query instanceof Closure ? $query() : $query;
    }

    public function minChildren(int $minChildren): self
    {
        $this->minChildren = $minChildren;
        return $this;
    }

    public function maxChildren(int $maxChildren): self
    {
        $this->maxChildren = $maxChildren;
        return $this;
    }

    public function toArray(): array
    {
        $params = [
            'type' => $this->type,
            'query' => $this->query instanceof QueryInterface ? $this->query->toArray() : $this->query,
        ];

        $this->applyScoreMode($params);

        if ($this->minChildren !== null) {
            $params['min_children'] = $this->minChildren;
        }

        if ($this->maxChildren !== null) {
            $params['max_children'] = $this->maxChildren;
        }

        $this->applyIgnoreUnmapped($params);
        $this->applyInnerHits($params);

        return ['has_child' => $params];
    }

    public function __clone(): void
    {
        if ($this->query instanceof QueryInterface) {
            $this->query = clone $this->query;
        }
    }
}
