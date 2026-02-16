<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;

final class CompositeAggregation implements AggregationInterface
{
    use HasSubAggregations;

    private array $sources = [];
    private ?int $size = null;
    private ?array $after = null;

    public function addSource(string $name, array $source): self
    {
        $this->sources[] = [$name => $source];
        return $this;
    }

    /** @param 'asc'|'desc'|null $order */
    public function termsSource(string $name, string $field, ?string $order = null): self
    {
        $source = ['terms' => ['field' => $field]];
        if ($order !== null) {
            $source['terms']['order'] = $order;
        }
        return $this->addSource($name, $source);
    }

    public function dateHistogramSource(
        string $name,
        string $field,
        string $calendarInterval,
        ?string $format = null,
    ): self {
        $source = ['date_histogram' => [
            'field' => $field,
            'calendar_interval' => $calendarInterval,
        ]];
        if ($format !== null) {
            $source['date_histogram']['format'] = $format;
        }
        return $this->addSource($name, $source);
    }

    public function histogramSource(string $name, string $field, int|float $interval): self
    {
        return $this->addSource($name, ['histogram' => [
            'field' => $field,
            'interval' => $interval,
        ]]);
    }

    public function size(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function after(array $after): self
    {
        $this->after = $after;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->sources === []) {
            throw new \InvalidArgumentException('CompositeAggregation requires at least one source.');
        }

        $params = ['sources' => $this->sources];

        if ($this->size !== null) {
            $params['size'] = $this->size;
        }

        if ($this->after !== null) {
            $params['after'] = $this->after;
        }

        $result = ['composite' => $params];
        $this->applySubAggregations($result);

        return $result;
    }
}
