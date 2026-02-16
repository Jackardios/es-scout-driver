<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;

final class TopHitsAggregation implements AggregationInterface
{
    private ?int $size = null;
    private ?int $from = null;
    private array $sort = [];
    private bool|array|null $source = null;
    private array $highlight = [];
    private ?bool $explain = null;
    private ?bool $version = null;

    public function size(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function from(int $from): self
    {
        $this->from = $from;
        return $this;
    }

    /** @param 'asc'|'desc' $order */
    public function sort(string $field, string $order = 'asc'): self
    {
        $this->sort[] = [$field => ['order' => $order]];
        return $this;
    }

    public function sortRaw(array $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    public function source(bool|array $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function highlight(array $highlight): self
    {
        $this->highlight = $highlight;
        return $this;
    }

    public function explain(bool $explain = true): self
    {
        $this->explain = $explain;
        return $this;
    }

    public function version(bool $version = true): self
    {
        $this->version = $version;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [];

        if ($this->size !== null) {
            $params['size'] = $this->size;
        }

        if ($this->from !== null) {
            $params['from'] = $this->from;
        }

        if ($this->sort !== []) {
            $params['sort'] = $this->sort;
        }

        if ($this->source !== null) {
            $params['_source'] = $this->source;
        }

        if ($this->highlight !== []) {
            $params['highlight'] = $this->highlight;
        }

        if ($this->explain !== null) {
            $params['explain'] = $this->explain;
        }

        if ($this->version !== null) {
            $params['version'] = $this->version;
        }

        return ['top_hits' => $params];
    }
}
