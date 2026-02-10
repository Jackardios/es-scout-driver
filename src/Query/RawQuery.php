<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;

final class RawQuery implements QueryInterface
{
    /** @var array<string, mixed> */
    private array $query;

    /** @param array<string, mixed> $query */
    public function __construct(array $query)
    {
        if ($query === []) {
            throw new InvalidQueryException('RawQuery requires a non-empty query array');
        }

        $this->query = $query;
    }

    /** @param array<string, mixed> $query */
    public function query(array $query): self
    {
        if ($query === []) {
            throw new InvalidQueryException('RawQuery requires a non-empty query array');
        }

        $this->query = $query;
        return $this;
    }

    public function toArray(): array
    {
        return $this->query;
    }
}
