<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

final class PercentilesAggregation extends FieldMetricAggregation
{
    /** @var array<float>|null */
    private ?array $percents = null;
    private ?int $compression = null;
    private ?bool $keyed = null;

    /** @param array<float> $percents */
    public function percents(array $percents): self
    {
        $this->percents = $percents;
        return $this;
    }

    public function compression(int $compression): self
    {
        $this->compression = $compression;
        return $this;
    }

    public function keyed(bool $keyed = true): self
    {
        $this->keyed = $keyed;
        return $this;
    }

    protected function type(): string
    {
        return 'percentiles';
    }

    /** @return array<string, mixed> */
    protected function options(): array
    {
        $options = [];

        if ($this->percents !== null) {
            $options['percents'] = $this->percents;
        }

        if ($this->compression !== null) {
            $options['tdigest'] = ['compression' => $this->compression];
        }

        if ($this->keyed !== null) {
            $options['keyed'] = $this->keyed;
        }

        return $options;
    }
}
