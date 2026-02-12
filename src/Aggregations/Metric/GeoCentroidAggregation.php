<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

final class GeoCentroidAggregation implements AggregationInterface
{
    public function __construct(private string $field) {}

    public function toArray(): array
    {
        return ['geo_centroid' => ['field' => $this->field]];
    }
}
