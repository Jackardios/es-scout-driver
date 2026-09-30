<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

final class ExtendedStatsAggregation extends FieldMetricAggregation
{
    private ?float $sigma = null;

    public function sigma(float $sigma): self
    {
        $this->sigma = $sigma;
        return $this;
    }

    protected function type(): string
    {
        return 'extended_stats';
    }

    /** @return array<string, mixed> */
    protected function options(): array
    {
        return $this->sigma !== null ? ['sigma' => $this->sigma] : [];
    }
}
