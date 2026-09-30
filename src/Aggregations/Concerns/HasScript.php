<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Aggregations\Concerns;

trait HasScript
{
    /** @var array<string, mixed>|null */
    private ?array $script = null;

    /** @param array<string, mixed> $script */
    public function script(array $script): static
    {
        $this->script = $script;
        return $this;
    }

    /** @param array<string, mixed> $params */
    protected function applyScript(array &$params): void
    {
        if ($this->script !== null) {
            $params['script'] = $this->script;
        }
    }
}
