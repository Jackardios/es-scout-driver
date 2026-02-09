<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query;

interface QueryInterface
{
    public function toArray(): array;
}
