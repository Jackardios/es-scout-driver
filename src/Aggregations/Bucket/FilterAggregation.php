<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class FilterAggregation implements AggregationInterface
{
    use HasSubAggregations;

    public function __construct(private QueryInterface|array $filter) {}

    public function toArray(): array
    {
        $filterArray = $this->filter instanceof QueryInterface
            ? $this->filter->toArray()
            : $this->filter;

        $result = ['filter' => $filterArray];
        $this->applySubAggregations($result);

        return $result;
    }
}
