<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class ScriptScoreQuery implements QueryInterface
{
    use HasBoost;

    private ?float $minScore = null;

    /** @param array<string, mixed> $script */
    public function __construct(
        private QueryInterface|array $query,
        private array $script,
    ) {}

    public function minScore(float $minScore): self
    {
        $this->minScore = $minScore;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'query' => $this->query instanceof QueryInterface ? $this->query->toArray() : $this->query,
            'script' => $this->script,
        ];

        if ($this->minScore !== null) {
            $params['min_score'] = $this->minScore;
        }

        $this->applyBoost($params);

        return ['script_score' => $params];
    }

    public function __clone(): void
    {
        if ($this->query instanceof QueryInterface) {
            $this->query = clone $this->query;
        }
    }
}
