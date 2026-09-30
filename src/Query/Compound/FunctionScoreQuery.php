<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Compound;

use Closure;
use Jackardios\EsScoutDriver\Enums\BoostMode;
use Jackardios\EsScoutDriver\Enums\FunctionScoreMode;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\SubQuery;
use stdClass;

final class FunctionScoreQuery implements QueryInterface
{
    use HasBoost;

    private QueryInterface|array|null $query = null;
    private array $functions = [];
    private ?string $functionScoreMode = null;
    private ?string $boostMode = null;
    private ?float $maxBoost = null;
    private ?float $minScore = null;

    public function __construct(QueryInterface|array|null $query = null)
    {
        $this->query = $query;
    }

    public function query(QueryInterface|Closure|array $query): self
    {
        $this->query = SubQuery::resolve($query);
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

    public function functionScoreMode(FunctionScoreMode|string $functionScoreMode): self
    {
        $this->functionScoreMode = $functionScoreMode instanceof FunctionScoreMode ? $functionScoreMode->value : $functionScoreMode;
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [];

        if ($this->query !== null) {
            $params['query'] = SubQuery::toArray($this->query);
        }

        if ($this->functions !== []) {
            $params['functions'] = array_map(
                static function (array $function): array {
                    if (($function['filter'] ?? null) instanceof QueryInterface) {
                        $function['filter'] = $function['filter']->toArray();
                    }

                    return $function;
                },
                $this->functions,
            );
        }

        if ($this->functionScoreMode !== null) {
            $params['score_mode'] = $this->functionScoreMode;
        }

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

        return ['function_score' => $params === [] ? new stdClass() : $params];
    }

    public function __clone(): void
    {
        if ($this->query instanceof QueryInterface) {
            $this->query = clone $this->query;
        }

        foreach ($this->functions as $index => $function) {
            if (($function['filter'] ?? null) instanceof QueryInterface) {
                $this->functions[$index]['filter'] = clone $function['filter'];
            }
        }
    }
}
