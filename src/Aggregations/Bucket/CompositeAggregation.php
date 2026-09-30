<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Bucket;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Aggregations\Concerns\HasSubAggregations;
use Jackardios\EsScoutDriver\Enums\SortOrder;

final class CompositeAggregation implements AggregationInterface
{
    use HasSubAggregations;

    private array $sources = [];
    private ?int $size = null;
    private ?array $after = null;

    public function addSource(string $name, array $source): self
    {
        foreach ($this->sources as $existing) {
            if (array_key_exists($name, $existing)) {
                throw new InvalidArgumentException(sprintf('CompositeAggregation already has a source named [%s].', $name));
            }
        }

        $this->sources[] = [$name => $source];
        return $this;
    }

    public function termsSource(string $name, string $field, SortOrder|string|null $order = null): self
    {
        $source = ['terms' => ['field' => $field]];
        if ($order !== null) {
            $source['terms']['order'] = $order instanceof SortOrder ? $order->value : $order;
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
        if ($interval <= 0) {
            throw new InvalidArgumentException('CompositeAggregation histogram source interval must be greater than 0.');
        }

        return $this->addSource($name, ['histogram' => [
            'field' => $field,
            'interval' => $interval,
        ]]);
    }

    public function size(int $size): self
    {
        if ($size < 1) {
            throw new InvalidArgumentException('CompositeAggregation size must be greater than 0.');
        }

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
            throw new InvalidArgumentException('CompositeAggregation requires at least one source.');
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
