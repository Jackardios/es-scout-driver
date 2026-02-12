<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;

final class NestedAggregation implements AggregationInterface
{
    use HasSubAggregations;

    public function __construct(private string $path) {}

    public function toArray(): array
    {
        $result = ['nested' => ['path' => $this->path]];
        $this->applySubAggregations($result);

        return $result;
    }
}
