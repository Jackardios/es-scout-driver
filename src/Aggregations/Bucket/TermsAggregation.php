<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;

final class TermsAggregation implements AggregationInterface
{
    use HasSubAggregations;

    private ?int $size = null;
    private ?int $minDocCount = null;
    private ?int $shardSize = null;
    private ?bool $showTermDocCountError = null;
    private ?array $order = null;
    private ?string $missing = null;
    private ?array $include = null;
    private ?array $exclude = null;

    public function __construct(private string $field) {}

    public function size(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function minDocCount(int $count): self
    {
        $this->minDocCount = $count;
        return $this;
    }

    public function shardSize(int $size): self
    {
        $this->shardSize = $size;
        return $this;
    }

    public function showTermDocCountError(bool $show = true): self
    {
        $this->showTermDocCountError = $show;
        return $this;
    }

    public function order(string $key, string $direction = 'asc'): self
    {
        $this->order = [$key => $direction];
        return $this;
    }

    public function orderByCount(string $direction = 'desc'): self
    {
        return $this->order('_count', $direction);
    }

    public function orderByKey(string $direction = 'asc'): self
    {
        return $this->order('_key', $direction);
    }

    public function missing(string $value): self
    {
        $this->missing = $value;
        return $this;
    }

    /** @param array<string>|string $patterns */
    public function include(array|string $patterns): self
    {
        $this->include = is_array($patterns) ? $patterns : [$patterns];
        return $this;
    }

    /** @param array<string>|string $patterns */
    public function exclude(array|string $patterns): self
    {
        $this->exclude = is_array($patterns) ? $patterns : [$patterns];
        return $this;
    }

    public function toArray(): array
    {
        $params = ['field' => $this->field];

        if ($this->size !== null) {
            $params['size'] = $this->size;
        }

        if ($this->minDocCount !== null) {
            $params['min_doc_count'] = $this->minDocCount;
        }

        if ($this->shardSize !== null) {
            $params['shard_size'] = $this->shardSize;
        }

        if ($this->showTermDocCountError !== null) {
            $params['show_term_doc_count_error'] = $this->showTermDocCountError;
        }

        if ($this->order !== null) {
            $params['order'] = $this->order;
        }

        if ($this->missing !== null) {
            $params['missing'] = $this->missing;
        }

        if ($this->include !== null) {
            $params['include'] = count($this->include) === 1 ? $this->include[0] : $this->include;
        }

        if ($this->exclude !== null) {
            $params['exclude'] = count($this->exclude) === 1 ? $this->exclude[0] : $this->exclude;
        }

        $result = ['terms' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
