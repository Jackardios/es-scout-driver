<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use InvalidArgumentException;

final class PercentilesAggregation extends FieldMetricAggregation
{
    /** @var array<int|float>|null */
    private ?array $percents = null;
    private int|float|null $compression = null;
    private ?bool $keyed = null;

    /** @param array<int|float> $percents */
    public function percents(array $percents): self
    {
        if ($percents === []) {
            throw new InvalidArgumentException('PercentilesAggregation percents must not be empty.');
        }

        foreach ($percents as $percent) {
            if ($percent < 0 || $percent > 100) {
                throw new InvalidArgumentException('PercentilesAggregation percents must be between 0 and 100.');
            }
        }

        $this->percents = $percents;
        return $this;
    }

    public function compression(int|float $compression): self
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
