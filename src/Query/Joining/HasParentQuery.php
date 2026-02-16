<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Joining;

use Closure;
use Jackardios\EsScoutDriver\Query\Concerns\HasIgnoreUnmapped;
use Jackardios\EsScoutDriver\Query\Concerns\HasInnerHits;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class HasParentQuery implements QueryInterface
{
    use HasIgnoreUnmapped;
    use HasInnerHits;

    private QueryInterface|array $query;
    private ?bool $score = null;

    /** @param QueryInterface|Closure|array $query */
    public function __construct(
        private string $parentType,
        QueryInterface|Closure|array $query,
    ) {
        $this->query = $query instanceof Closure ? $query() : $query;
    }

    public function score(bool $score = true): self
    {
        $this->score = $score;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'parent_type' => $this->parentType,
            'query' => $this->query instanceof QueryInterface ? $this->query->toArray() : $this->query,
        ];

        if ($this->score !== null) {
            $params['score'] = $this->score;
        }

        $this->applyIgnoreUnmapped($params);
        $this->applyInnerHits($params);

        return ['has_parent' => $params];
    }

    public function __clone(): void
    {
        if ($this->query instanceof QueryInterface) {
            $this->query = clone $this->query;
        }
    }
}
