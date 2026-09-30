<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Concerns;

use Jackardios\EsScoutDriver\Enums\SortOrder;

trait HasBucketOrder
{
    /** @var array<string, string>|null */
    private ?array $order = null;

    public function order(string $key, SortOrder|string $direction = 'asc'): static
    {
        $this->order = [$key => $direction instanceof SortOrder ? $direction->value : $direction];
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyOrder(array &$params): void
    {
        if ($this->order !== null) {
            $params['order'] = $this->order;
        }
    }
}
