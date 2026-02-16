<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;
use stdClass;

final class ReverseNestedAggregation implements AggregationInterface
{
    use HasSubAggregations;

    private ?string $path = null;

    public function path(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = $this->path !== null ? ['path' => $this->path] : new stdClass();
        $result = ['reverse_nested' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
