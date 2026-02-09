<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use stdClass;

trait HasInnerHits
{
    private ?array $innerHits = null;

    public function innerHits(array $innerHits = []): self
    {
        $this->innerHits = $innerHits;
        return $this;
    }

    protected function applyInnerHits(array &$params): void
    {
        if ($this->innerHits !== null) {
            $params['inner_hits'] = $this->innerHits ?: new stdClass();
        }
    }
}
