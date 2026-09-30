<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasMissing;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasRanges;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasScript;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;

final class RangeAggregation implements AggregationInterface
{
    use HasMissing;
    use HasRanges;
    use HasScript;
    use HasSubAggregations;

    private ?bool $keyed = null;

    public function __construct(private string $field) {}

    public function range(int|float|null $from = null, int|float|null $to = null, ?string $key = null): self
    {
        return $this->addRange($from, $to, $key);
    }

    public function keyed(bool $keyed = true): self
    {
        $this->keyed = $keyed;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->ranges === []) {
            throw new \InvalidArgumentException('RangeAggregation requires at least one range.');
        }

        $params = [
            'field' => $this->field,
            'ranges' => $this->ranges,
        ];

        $this->applyMissing($params);

        if ($this->keyed !== null) {
            $params['keyed'] = $this->keyed;
        }

        $this->applyScript($params);

        $result = ['range' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
