<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Metric;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasMissing;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasScript;

/**
 * A metric aggregation over one field, with missing and script options.
 *
 * @internal
 */
abstract class FieldMetricAggregation implements AggregationInterface
{
    use HasMissing;
    use HasScript;

    public function __construct(private string $field) {}

    abstract protected function type(): string;

    /**
     * Options sent between the field and the missing and script options.
     *
     * @return array<string, mixed>
     */
    protected function options(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = ['field' => $this->field, ...$this->options()];
        $this->applyMissing($params);
        $this->applyScript($params);

        return [$this->type() => $params];
    }
}
