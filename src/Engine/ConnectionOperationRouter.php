<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Elastic\Elasticsearch\Client;
use InvalidArgumentException;

/** @internal */
final class ConnectionOperationRouter
{
    public const DEFAULT_CONNECTION = '__default__';

    public function normalize(?string $connection): string
    {
        return $connection !== null && $connection !== ''
            ? $connection
            : self::DEFAULT_CONNECTION;
    }

    /**
     * @template TItem
     * @param iterable<int, TItem> $items
     * @param callable(TItem): (?string) $connectionResolver
     * @return array<string, list<TItem>>
     */
    public function groupByConnection(iterable $items, callable $connectionResolver): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $connection = $this->normalize($connectionResolver($item));
            $grouped[$connection][] = $item;
        }

        return $grouped;
    }

    public function resolveClientForConnection(string $connection, Client $defaultClient, ?string $defaultConnectionName = null): Client
    {
        if ($connection === self::DEFAULT_CONNECTION || $connection === $defaultConnectionName) {
            return $defaultClient;
        }

        return $this->client($connection);
    }

    public function client(string $connection): Client
    {
        $binding = "elastic.client.connection.$connection";

        if (!app()->bound($binding)) {
            throw new InvalidArgumentException(sprintf(
                'Elasticsearch connection [%s] is not configured in elastic.client.connections.',
                $connection,
            ));
        }

        return app($binding);
    }
}
