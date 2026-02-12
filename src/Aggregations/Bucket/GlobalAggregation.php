<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;
use stdClass;

final class GlobalAggregation implements AggregationInterface
{
    use HasSubAggregations;

    public function toArray(): array
    {
        $result = ['global' => new stdClass()];
        $this->applySubAggregations($result);

        return $result;
    }
}
