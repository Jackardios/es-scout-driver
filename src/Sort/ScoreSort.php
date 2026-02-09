<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort;

use Jackardios\EsScoutDriver\Enums\SortOrder;

final class ScoreSort implements SortInterface
{
    private string $order = 'desc';

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

    public function toArray(): array
    {
        return ['_score' => $this->order];
    }
}
