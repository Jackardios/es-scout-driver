<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class FiltersAggregation implements AggregationInterface
{
    use HasSubAggregations;

    /** @var array<int|string, QueryInterface|array> */
    private array $filters = [];
    private ?bool $otherBucket = null;
    private ?string $otherBucketKey = null;

    /** @throws InvalidArgumentException when an integer name would turn the filters into a JSON list */
    public function filter(string $name, QueryInterface|array $filter): self
    {
        $filters = $this->filters;
        $filters[$name] = $filter;

        if (array_is_list($filters)) {
            throw new InvalidArgumentException(sprintf(
                'FiltersAggregation filter name [%s] would send the filters as an anonymous list; use a name that is not an integer.',
                $name,
            ));
        }

        $this->filters = $filters;
        return $this;
    }

    /** @param array<int|string, QueryInterface|array> $filters Named filters, or a list of anonymous filters */
    public function filters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    public function otherBucket(bool $enabled = true): self
    {
        $this->otherBucket = $enabled;
        return $this;
    }

    public function otherBucketKey(string $key): self
    {
        $this->otherBucketKey = $key;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->filters === []) {
            throw new InvalidArgumentException('FiltersAggregation requires at least one filter.');
        }

        $filtersArray = [];
        foreach ($this->filters as $name => $filter) {
            $filtersArray[$name] = $filter instanceof QueryInterface
                ? $filter->toArray()
                : $filter;
        }

        $params = ['filters' => $filtersArray];

        if ($this->otherBucket !== null) {
            $params['other_bucket'] = $this->otherBucket;
        }

        if ($this->otherBucketKey !== null) {
            $params['other_bucket_key'] = $this->otherBucketKey;
        }

        $result = ['filters' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
