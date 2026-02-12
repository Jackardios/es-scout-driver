<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

final class GeoBoundsAggregation implements AggregationInterface
{
    private ?bool $wrapLongitude = null;

    public function __construct(private string $field) {}

    public function wrapLongitude(bool $wrap = true): self
    {
        $this->wrapLongitude = $wrap;
        return $this;
    }

    public function toArray(): array
    {
        $params = ['field' => $this->field];

        if ($this->wrapLongitude !== null) {
            $params['wrap_longitude'] = $this->wrapLongitude;
        }

        return ['geo_bounds' => $params];
    }
}
