<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Concerns;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

trait HasSubAggregations
{
    /** @var array<string, AggregationInterface|array> */
    private array $subAggregations = [];

    public function agg(string $name, AggregationInterface|array $aggregation): self
    {
        $this->subAggregations[$name] = $aggregation;
        return $this;
    }

    protected function applySubAggregations(array &$result): void
    {
        if ($this->subAggregations === []) {
            return;
        }

        $aggs = [];
        foreach ($this->subAggregations as $name => $aggregation) {
            $aggs[$name] = $aggregation instanceof AggregationInterface
                ? $aggregation->toArray()
                : $aggregation;
        }

        $result['aggs'] = $aggs;
    }
}
