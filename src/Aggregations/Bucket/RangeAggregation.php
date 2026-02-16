<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;

final class RangeAggregation implements AggregationInterface
{
    use HasSubAggregations;

    /** @var array<int, array{from?: int|float, to?: int|float, key?: string}> */
    private array $ranges = [];
    private ?string $missing = null;
    private ?bool $keyed = null;
    private ?array $script = null;

    public function __construct(private string $field) {}

    /** @param array<int, array{from?: int|float, to?: int|float, key?: string}> $ranges */
    public function ranges(array $ranges): self
    {
        $this->ranges = $ranges;
        return $this;
    }

    public function range(int|float|null $from = null, int|float|null $to = null, ?string $key = null): self
    {
        $range = [];
        if ($from !== null) {
            $range['from'] = $from;
        }
        if ($to !== null) {
            $range['to'] = $to;
        }
        if ($key !== null) {
            $range['key'] = $key;
        }
        $this->ranges[] = $range;
        return $this;
    }

    public function missing(string $value): self
    {
        $this->missing = $value;
        return $this;
    }

    public function keyed(bool $keyed = true): self
    {
        $this->keyed = $keyed;
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
        if ($this->ranges === []) {
            throw new \InvalidArgumentException('RangeAggregation requires at least one range.');
        }

        $params = [
            'field' => $this->field,
            'ranges' => $this->ranges,
        ];

        if ($this->missing !== null) {
            $params['missing'] = $this->missing;
        }

        if ($this->keyed !== null) {
            $params['keyed'] = $this->keyed;
        }

        if ($this->script !== null) {
            $params['script'] = $this->script;
        }

        $result = ['range' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
