<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

final class StatsAggregation extends FieldMetricAggregation
{
    protected function type(): string
    {
        return 'stats';
    }
}
