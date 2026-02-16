<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query;

interface QueryInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
