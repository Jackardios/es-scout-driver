<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Sort;

use Jackardios\EsScoutDriver\Enums\SortOrder;

final class ScriptSort implements SortInterface
{
    private string $order = 'asc';
    private ?string $mode = null;
    private ?array $nested = null;

    public function __construct(
        private array $script,
        private string $type,
    ) {}

    public function asc(): self
    {
        $this->order = 'asc';
        return $this;
    }

    public function desc(): self
    {
        $this->order = 'desc';
        return $this;
    }

    public function order(SortOrder|string $direction): self
    {
        $this->order = $direction instanceof SortOrder ? $direction->value : $direction;
        return $this;
    }

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
