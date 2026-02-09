<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort;

use Jackardios\EsScoutDriver\Enums\SortOrder;

final class FieldSort implements SortInterface
{
    private string $order = 'asc';
    private ?string $missing = null;
    private ?string $mode = null;
    private ?string $unmappedType = null;
    private ?array $nested = null;
    private ?string $numericType = null;
    private ?string $format = null;

    public function __construct(private string $field) {}

    public function asc(): self
    {
        $this->order = 'asc';
        return $this;
    }

    public function desc(): self
    {
        $this->order = 'desc';
        return $this;
    }

    public function order(SortOrder|string $direction): self
    {
        $this->order = $direction instanceof SortOrder ? $direction->value : $direction;
        return $this;
    }

    public function missing(string $value): self
    {
        $this->missing = $value;
        return $this;
    }

    public function missingFirst(): self
    {
        return $this->missing('_first');
    }

    public function missingLast(): self
    {
        return $this->missing('_last');
    }

    public function mode(string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function unmappedType(string $type): self
    {
        $this->unmappedType = $type;
        return $this;
    }

    public function nested(array $nested): self
    {
        $this->nested = $nested;
        return $this;
    }

    public function numericType(string $type): self
    {
        $this->numericType = $type;
        return $this;
    }

    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function toArray(): array
    {
        $hasOptions = $this->missing !== null
            || $this->mode !== null
            || $this->unmappedType !== null
            || $this->nested !== null
            || $this->numericType !== null
            || $this->format !== null;

        if (!$hasOptions) {
            return [$this->field => $this->order];
        }

        $params = ['order' => $this->order];

        if ($this->missing !== null) {
            $params['missing'] = $this->missing;
        }

        if ($this->mode !== null) {
            $params['mode'] = $this->mode;
        }

        if ($this->unmappedType !== null) {
            $params['unmapped_type'] = $this->unmappedType;
        }

        if ($this->nested !== null) {
            $params['nested'] = $this->nested;
        }

        if ($this->numericType !== null) {
            $params['numeric_type'] = $this->numericType;
        }

        if ($this->format !== null) {
            $params['format'] = $this->format;
        }

        return [$this->field => $params];
    }
}
