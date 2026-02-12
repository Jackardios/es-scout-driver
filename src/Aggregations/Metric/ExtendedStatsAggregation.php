<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

final class ExtendedStatsAggregation implements AggregationInterface
{
    private ?float $sigma = null;
    private ?string $missing = null;
    private ?array $script = null;

    public function __construct(private string $field) {}

    public function sigma(float $sigma): self
    {
        $this->sigma = $sigma;
        return $this;
    }

    public function missing(string $value): self
    {
        $this->missing = $value;
        return $this;
    }

    public function script(array $script): self
    {
        $this->script = $script;
        return $this;
    }

    public function toArray(): array
    {
        $params = ['field' => $this->field];

        if ($this->sigma !== null) {
            $params['sigma'] = $this->sigma;
        }

        if ($this->missing !== null) {
            $params['missing'] = $this->missing;
        }

        if ($this->script !== null) {
            $params['script'] = $this->script;
        }

        return ['extended_stats' => $params];
    }
}
