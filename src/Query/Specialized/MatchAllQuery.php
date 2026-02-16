<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use stdClass;

final class MatchAllQuery implements QueryInterface
{
    use HasBoost;

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->boost !== null) {
            return ['match_all' => ['boost' => $this->boost]];
        }

        return ['match_all' => new stdClass()];
    }
}
