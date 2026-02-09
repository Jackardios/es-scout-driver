<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations;

interface AggregationInterface
{
    public function toArray(): array;
}
