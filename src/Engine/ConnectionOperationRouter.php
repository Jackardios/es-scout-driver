<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

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
     * @return array<string, array<int, TItem>>
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

    /**
     * @template TClient of object
     * @param TClient $defaultClient
     * @return TClient
     */
    public function resolveClientForConnection(string $connection, object $defaultClient, ?string $defaultConnectionName = null): object
    {
        if ($connection === self::DEFAULT_CONNECTION) {
            return $defaultClient;
        }

        if (
            $defaultConnectionName !== null
            && $defaultConnectionName !== ''
            && $connection === $defaultConnectionName
        ) {
            return $defaultClient;
        }

        return app("elastic.client.connection.$connection");
    }
}
