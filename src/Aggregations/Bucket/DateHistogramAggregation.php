<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasBucketOrder;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasMissing;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;

final class DateHistogramAggregation implements AggregationInterface
{
    use HasBucketOrder;
    use HasMissing;
    use HasSubAggregations;

    private string $interval;
    private string $intervalType = 'calendar_interval';
    private ?string $format = null;
    private ?string $timeZone = null;
    private ?int $minDocCount = null;
    private ?array $extendedBounds = null;
    private ?array $hardBounds = null;
    private ?string $offset = null;
    private ?bool $keyed = null;

    public function __construct(
        private string $field,
        string $calendarInterval,
    ) {
        $this->interval = $calendarInterval;
    }

    /** Replaces the fixed interval, if one was set. */
    public function calendarInterval(string $interval): self
    {
        $this->interval = $interval;
        $this->intervalType = 'calendar_interval';
        return $this;
    }

    /** Replaces the calendar interval passed to the constructor. */
    public function fixedInterval(string $interval): self
    {
        $this->interval = $interval;
        $this->intervalType = 'fixed_interval';
        return $this;
    }

    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function timeZone(string $timeZone): self
    {
        $this->timeZone = $timeZone;
        return $this;
    }

    public function minDocCount(int $count): self
    {
        $this->minDocCount = $count;
        return $this;
    }

    public function extendedBounds(string $min, string $max): self
    {
        $this->extendedBounds = ['min' => $min, 'max' => $max];
        return $this;
    }

    public function hardBounds(string $min, string $max): self
    {
        $this->hardBounds = ['min' => $min, 'max' => $max];
        return $this;
    }

    public function offset(string $offset): self
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
            $this->intervalType => $this->interval,
        ];

        if ($this->format !== null) {
            $params['format'] = $this->format;
        }

        if ($this->timeZone !== null) {
            $params['time_zone'] = $this->timeZone;
        }

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

        $result = ['date_histogram' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
