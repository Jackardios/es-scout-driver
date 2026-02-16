<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use stdClass;

trait HasInnerHits
{
    /** @var array<string, mixed>|null */
    private ?array $innerHits = null;

    /** @param array<string, mixed> $innerHits */
    public function innerHits(array $innerHits = []): static
    {
        $this->innerHits = $innerHits;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyInnerHits(array &$params): void
    {
        if ($this->innerHits !== null) {
            $params['inner_hits'] = $this->innerHits ?: new stdClass();
        }
    }
}
