<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

final class SumAggregation implements AggregationInterface
{
    private ?string $missing = null;
    private ?array $script = null;

    public function __construct(private string $field) {}

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

        if ($this->missing !== null) {
            $params['missing'] = $this->missing;
        }

        if ($this->script !== null) {
            $params['script'] = $this->script;
        }

        return ['sum' => $params];
    }
}
