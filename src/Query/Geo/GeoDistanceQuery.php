<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Geo;

use Jackardios\EsScoutDriver\Enums\DistanceType;
use Jackardios\EsScoutDriver\Query\Concerns\HasIgnoreUnmapped;
use Jackardios\EsScoutDriver\Query\Concerns\HasValidationMethod;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class GeoDistanceQuery implements QueryInterface
{
    use HasIgnoreUnmapped;
    use HasValidationMethod;

    private ?string $distanceType = null;

    public function __construct(
        private string $field,
        private float $lat,
        private float $lon,
        private string $distance,
    ) {}

    public function distanceType(DistanceType|string $distanceType): self
    {
        $this->distanceType = $distanceType instanceof DistanceType ? $distanceType->value : $distanceType;
        return $this;
    }

    public function toArray(): array
    {
        $params = [
            $this->field => ['lat' => $this->lat, 'lon' => $this->lon],
            'distance' => $this->distance,
        ];

        if ($this->distanceType !== null) {
            $params['distance_type'] = $this->distanceType;
        }

        $this->applyValidationMethod($params);
        $this->applyIgnoreUnmapped($params);

        return ['geo_distance' => $params];
    }
}
