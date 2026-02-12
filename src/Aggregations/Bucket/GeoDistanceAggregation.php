<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;
use Jackardios\EsScoutDriver\Enums\DistanceType;

final class GeoDistanceAggregation implements AggregationInterface
{
    use HasSubAggregations;

    /** @var array<int, array{from?: int|float, to?: int|float, key?: string}> */
    private array $ranges = [];
    private ?string $unit = null;
    private ?string $distanceType = null;
    private ?bool $keyed = null;

    public function __construct(
        private string $field,
        private float $lat,
        private float $lon,
    ) {}

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

    /** @param array<int, array{from?: int|float, to?: int|float, key?: string}> $ranges */
    public function ranges(array $ranges): self
    {
        $this->ranges = $ranges;
        return $this;
    }

    public function unit(string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function distanceType(DistanceType|string $distanceType): self
    {
        $this->distanceType = $distanceType instanceof DistanceType ? $distanceType->value : $distanceType;
        return $this;
    }

    public function keyed(bool $keyed = true): self
    {
        $this->keyed = $keyed;
        return $this;
    }

    public function toArray(): array
    {
        if ($this->ranges === []) {
            throw new InvalidArgumentException('GeoDistanceAggregation requires at least one range.');
        }

        $params = [
            'field' => $this->field,
            'origin' => ['lat' => $this->lat, 'lon' => $this->lon],
            'ranges' => $this->ranges,
        ];

        if ($this->unit !== null) {
            $params['unit'] = $this->unit;
        }

        if ($this->distanceType !== null) {
            $params['distance_type'] = $this->distanceType;
        }

        if ($this->keyed !== null) {
            $params['keyed'] = $this->keyed;
        }

        $result = ['geo_distance' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
