<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Collection;

interface EngineInterface
{
    /**
     * Update the given models in the search index.
     *
     * @param Collection $models
     */
    public function update($models): void;

    /**
     * Remove the given models from the search index.
     *
     * @param Collection $models
     */
    public function delete($models): void;

    /**
     * Perform a raw search against the engine.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function searchRaw(array $params): array;

    /**
     * Open a Point in Time for the given index.
     */
    public function openPointInTime(string $indexName, ?string $keepAlive = null): string;

    /**
     * Close a Point in Time.
     */
    public function closePointInTime(string $pointInTimeId): void;

    /**
     * Perform a count query against the engine.
     *
     * @param array<string, mixed> $params
     */
    public function countRaw(array $params): int;

    /**
     * Delete documents matching a query.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function deleteByQueryRaw(array $params): array;

    /**
     * Update documents matching a query.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function updateByQueryRaw(array $params): array;

    /**
     * Switch to a different Elasticsearch connection.
     */
    public function connection(string $connection): static;

    /**
     * Get the underlying Elasticsearch client, if available.
     */
    public function getClient(): ?Client;
}
