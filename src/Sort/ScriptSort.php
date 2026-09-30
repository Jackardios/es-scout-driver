<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort;

use Jackardios\EsScoutDriver\Sort\Concerns\HasOrder;

final class ScriptSort implements SortInterface
{
    use HasOrder;

    private ?string $mode = null;
    private ?array $nested = null;

    public function __construct(
        private array $script,
        private string $type,
    ) {}

    public function mode(string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function nested(array $nested): self
    {
        $this->nested = $nested;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'type' => $this->type,
            'script' => $this->script,
            'order' => $this->order,
        ];

        if ($this->mode !== null) {
            $params['mode'] = $this->mode;
        }

        if ($this->nested !== null) {
            $params['nested'] = $this->nested;
        }

        return ['_script' => $params];
    }
}
