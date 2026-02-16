<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

final class PercentilesAggregation implements AggregationInterface
{
    /** @var array<float>|null */
    private ?array $percents = null;
    private ?int $compression = null;
    private ?string $missing = null;
    private ?array $script = null;
    private ?bool $keyed = null;

    public function __construct(private string $field) {}

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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['field' => $this->field];

        if ($this->percents !== null) {
            $params['percents'] = $this->percents;
        }

        if ($this->compression !== null) {
            $params['tdigest'] = ['compression' => $this->compression];
        }

        if ($this->keyed !== null) {
            $params['keyed'] = $this->keyed;
        }

        if ($this->missing !== null) {
            $params['missing'] = $this->missing;
        }

        if ($this->script !== null) {
            $params['script'] = $this->script;
        }

        return ['percentiles' => $params];
    }
}
