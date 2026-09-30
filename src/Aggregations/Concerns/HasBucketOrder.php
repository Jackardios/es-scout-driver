<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Concerns;

trait HasBucketOrder
{
    /** @var array<string, string>|null */
    private ?array $order = null;

    /** @param 'asc'|'desc' $direction */
    public function order(string $key, string $direction = 'asc'): static
    {
        $this->order = [$key => $direction];
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
