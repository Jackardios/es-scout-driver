<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort;

interface SortInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
