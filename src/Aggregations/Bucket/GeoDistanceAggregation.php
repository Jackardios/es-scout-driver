<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasRanges;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;
use Jackardios\EsScoutDriver\Enums\DistanceType;

final class GeoDistanceAggregation implements AggregationInterface
{
    use HasRanges;
    use HasSubAggregations;

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
        return $this->addRange($from, $to, $key);
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

    /** @return array<string, mixed> */
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
