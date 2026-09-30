<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasBucketOrder;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasMissing;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;

final class HistogramAggregation implements AggregationInterface
{
    use HasBucketOrder;
    use HasMissing;
    use HasSubAggregations;

    private ?int $minDocCount = null;
    private ?array $extendedBounds = null;
    private ?array $hardBounds = null;
    private ?int $offset = null;
    private ?bool $keyed = null;

    public function __construct(
        private string $field,
        private int|float $interval,
    ) {
        if ($interval <= 0) {
            throw new InvalidArgumentException('HistogramAggregation interval must be greater than 0.');
        }
    }

    public function minDocCount(int $count): self
    {
        $this->minDocCount = $count;
        return $this;
    }

    public function extendedBounds(int|float $min, int|float $max): self
    {
        $this->extendedBounds = ['min' => $min, 'max' => $max];
        return $this;
    }

    public function hardBounds(int|float $min, int|float $max): self
    {
        $this->hardBounds = ['min' => $min, 'max' => $max];
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function keyed(bool $keyed = true): self
    {
        $this->keyed = $keyed;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'field' => $this->field,
            'interval' => $this->interval,
        ];

        if ($this->minDocCount !== null) {
            $params['min_doc_count'] = $this->minDocCount;
        }

        if ($this->extendedBounds !== null) {
            $params['extended_bounds'] = $this->extendedBounds;
        }

        if ($this->hardBounds !== null) {
            $params['hard_bounds'] = $this->hardBounds;
        }

        if ($this->offset !== null) {
            $params['offset'] = $this->offset;
        }

        $this->applyOrder($params);
        $this->applyMissing($params);

        if ($this->keyed !== null) {
            $params['keyed'] = $this->keyed;
        }

        $result = ['histogram' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
