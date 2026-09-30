<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort;

use Jackardios\EsScoutDriver\Sort\Concerns\HasOrder;

final class ScoreSort implements SortInterface
{
    use HasOrder;

    public function __construct()
    {
        $this->order = 'desc';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['_score' => $this->order];
    }
}
