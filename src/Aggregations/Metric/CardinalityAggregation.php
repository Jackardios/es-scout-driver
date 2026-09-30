<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use InvalidArgumentException;

final class CardinalityAggregation extends FieldMetricAggregation
{
    private ?int $precisionThreshold = null;

    public function precisionThreshold(int $threshold): self
    {
        if ($threshold < 0) {
            throw new InvalidArgumentException('CardinalityAggregation precision threshold must not be negative.');
        }

        $this->precisionThreshold = $threshold;
        return $this;
    }

    protected function type(): string
    {
        return 'cardinality';
    }

    /** @return array<string, mixed> */
    protected function options(): array
    {
        return $this->precisionThreshold !== null ? ['precision_threshold' => $this->precisionThreshold] : [];
    }
}
