<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Concerns;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

trait HasSubAggregations
{
    /** @var array<int|string, AggregationInterface|array<string, mixed>> Integer-like names become integer keys */
    private array $subAggregations = [];

    /**
     * @param AggregationInterface|array<string, mixed> $aggregation
     *
     * @throws InvalidArgumentException when an integer name would turn the sub-aggregations into a JSON list
     */
    public function agg(string $name, AggregationInterface|array $aggregation): static
    {
        $subAggregations = $this->subAggregations;
        $subAggregations[$name] = $aggregation;

        if (array_is_list($subAggregations)) {
            throw new InvalidArgumentException(sprintf(
                'Sub-aggregation name [%s] would send the sub-aggregations as a JSON list; use a name that is not an integer.',
                $name,
            ));
        }

        $this->subAggregations = $subAggregations;
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
