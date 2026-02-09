<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Query\QueryInterface;

final class MatchNoneQuery implements QueryInterface
{
    public function toArray(): array
    {
        return ['match_none' => new \stdClass()];
    }
}
