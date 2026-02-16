<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations;

interface AggregationInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
