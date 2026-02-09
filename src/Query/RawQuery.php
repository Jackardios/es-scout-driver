<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query;

final class RawQuery implements QueryInterface
{
    /** @var array<string, mixed> */
    private array $query;

    /** @param array<string, mixed> $query */
    public function __construct(array $query = [])
    {
        $this->query = $query;
    }

    /** @param array<string, mixed> $query */
    public function query(array $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function toArray(): array
    {
        return $this->query;
    }
}
