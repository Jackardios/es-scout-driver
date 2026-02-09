<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Compound;

use Closure;
use Jackardios\EsScoutDriver\Enums\BoostMode;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasFunctionScoreMode;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class FunctionScoreQuery implements QueryInterface
{
    use HasBoost;
    use HasFunctionScoreMode;

    private QueryInterface|array|null $query = null;
    private array $functions = [];
    private ?string $boostMode = null;
    private ?float $maxBoost = null;
    private ?float $minScore = null;

    public function __construct(QueryInterface|array|null $query = null)
    {
        $this->query = $query;
    }

    /** @param QueryInterface|Closure|array $query */
    public function query(QueryInterface|Closure|array $query): self
    {
        $this->query = $query instanceof Closure ? $query() : $query;
        return $this;
    }

    public function functions(array ...$functions): self
    {
        $this->functions = array_values($functions);
        return $this;
    }

    public function addFunction(array $function): self
    {
        $this->functions[] = $function;
        return $this;
    }

    public function boostMode(BoostMode|string $boostMode): self
    {
        $this->boostMode = $boostMode instanceof BoostMode ? $boostMode->value : $boostMode;
        return $this;
    }

    public function maxBoost(float $maxBoost): self
    {
        $this->maxBoost = $maxBoost;
        return $this;
    }

    public function minScore(float $minScore): self
    {
        $this->minScore = $minScore;
        return $this;
    }

    public function toArray(): array
    {
        $params = [];

        if ($this->query !== null) {
            $params['query'] = $this->query instanceof QueryInterface ? $this->query->toArray() : $this->query;
        }

        if ($this->functions !== []) {
            $params['functions'] = $this->functions;
        }

        $this->applyScoreMode($params);

        if ($this->boostMode !== null) {
            $params['boost_mode'] = $this->boostMode;
        }

        if ($this->maxBoost !== null) {
            $params['max_boost'] = $this->maxBoost;
        }

        if ($this->minScore !== null) {
            $params['min_score'] = $this->minScore;
        }

        $this->applyBoost($params);

        return ['function_score' => $params];
    }

    public function __clone(): void
    {
        if ($this->query instanceof QueryInterface) {
            $this->query = clone $this->query;
        }
    }
}
