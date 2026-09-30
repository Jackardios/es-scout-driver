<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort\Concerns;

use Jackardios\EsScoutDriver\Enums\SortOrder;

trait HasOrder
{
    private string $order = 'asc';

    public function asc(): static
    {
        $this->order = 'asc';
        return $this;
    }

    public function desc(): static
    {
        $this->order = 'desc';
        return $this;
    }

    public function order(SortOrder|string $direction): static
    {
        $this->order = $direction instanceof SortOrder ? $direction->value : $direction;
        return $this;
    }
}
