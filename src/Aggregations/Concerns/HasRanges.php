<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Concerns;

trait HasRanges
{
    /** @var array<int, array{from?: int|float|string, to?: int|float|string, key?: string}> */
    private array $ranges = [];

    /** @param array<int, array{from?: int|float|string, to?: int|float|string, key?: string}> $ranges */
    public function ranges(array $ranges): static
    {
        $this->ranges = $ranges;
        return $this;
    }

    protected function addRange(int|float|string|null $from, int|float|string|null $to, ?string $key): static
    {
        $range = [];
        if ($from !== null) {
            $range['from'] = $from;
        }
        if ($to !== null) {
            $range['to'] = $to;
        }
        if ($key !== null) {
            $range['key'] = $key;
        }
        $this->ranges[] = $range;
        return $this;
    }
}
