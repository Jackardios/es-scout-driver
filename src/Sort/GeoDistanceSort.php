<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort;

use Jackardios\EsScoutDriver\Enums\SortOrder;

final class GeoDistanceSort implements SortInterface
{
    private string $order = 'asc';
    private string $unit = 'km';
    private ?string $mode = null;
    private ?string $distanceType = null;
    private ?bool $ignoreUnmapped = null;

    public function __construct(
        private string $field,
        private float $lat,
        private float $lon,
    ) {}

    public function asc(): self
    {
        $this->order = 'asc';
        return $this;
    }

    public function desc(): self
    {
        $this->order = 'desc';
        return $this;
    }

    public function order(SortOrder|string $direction): self
    {
        $this->order = $direction instanceof SortOrder ? $direction->value : $direction;
        return $this;
    }

    public function unit(string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function mode(string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function distanceType(string $type): self
    {
        $this->distanceType = $type;
        return $this;
    }

    public function ignoreUnmapped(bool $ignore = true): self
    {
        $this->ignoreUnmapped = $ignore;
        return $this;
    }

    public function toArray(): array
    {
        $params = [
            $this->field => ['lat' => $this->lat, 'lon' => $this->lon],
            'order' => $this->order,
            'unit' => $this->unit,
        ];

        if ($this->mode !== null) {
            $params['mode'] = $this->mode;
        }

        if ($this->distanceType !== null) {
            $params['distance_type'] = $this->distanceType;
        }

        if ($this->ignoreUnmapped !== null) {
            $params['ignore_unmapped'] = $this->ignoreUnmapped;
        }

        return ['_geo_distance' => $params];
    }
}
