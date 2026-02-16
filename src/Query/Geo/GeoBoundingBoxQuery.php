<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Geo;

use Jackardios\EsScoutDriver\Query\Concerns\HasIgnoreUnmapped;
use Jackardios\EsScoutDriver\Query\Concerns\HasValidationMethod;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class GeoBoundingBoxQuery implements QueryInterface
{
    use HasIgnoreUnmapped;
    use HasValidationMethod;

    public function __construct(
        private string $field,
        private float $topLeftLat,
        private float $topLeftLon,
        private float $bottomRightLat,
        private float $bottomRightLon,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            $this->field => [
                'top_left' => ['lat' => $this->topLeftLat, 'lon' => $this->topLeftLon],
                'bottom_right' => ['lat' => $this->bottomRightLat, 'lon' => $this->bottomRightLon],
            ],
        ];

        $this->applyValidationMethod($params);
        $this->applyIgnoreUnmapped($params);

        return ['geo_bounding_box' => $params];
    }
}
