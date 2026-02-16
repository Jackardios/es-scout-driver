<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Concerns;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

trait HasSubAggregations
{
    /** @var array<string, AggregationInterface|array<string, mixed>> */
    private array $subAggregations = [];

    /** @param AggregationInterface|array<string, mixed> $aggregation */
    public function agg(string $name, AggregationInterface|array $aggregation): static
    {
        $this->subAggregations[$name] = $aggregation;
        return $this;
    }

    /** @param array<string, mixed> $result */
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
