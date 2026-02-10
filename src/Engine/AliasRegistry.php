<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Elastic\Elasticsearch\Client;

/**
 * Registry for Elasticsearch index aliases with TTL caching.
 * Shared between SearchBuilder clones to prevent N+1 HTTP requests.
 */
class AliasRegistry
{
    private const DEFAULT_TTL_SECONDS = 300; // 5 minutes

    /** @var array<string, string> alias/real index -> registered index */
    private array $aliasMap = [];

    /** @var array<string, bool> registered indices */
    private array $registeredIndices = [];

    private ?int $lastFetchTime = null;
    private bool $fetched = false;
    private bool $fetchInProgress = false;

    public function __construct(
        private ?Client $client = null,
        private int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {}

    public function registerIndex(string $indexName): void
    {
        if (!isset($this->registeredIndices[$indexName])) {
            $this->fetched = false;
            $this->lastFetchTime = null;
            $this->aliasMap = [];
        }

        $this->registeredIndices[$indexName] = true;
    }

    public function resolve(string $indexName): string
    {
        if (isset($this->registeredIndices[$indexName])) {
            return $indexName;
        }

        $this->ensureFetched();

        return $this->aliasMap[$indexName] ?? $indexName;
    }

    private function ensureFetched(): void
    {
        if ($this->client === null) {
            return;
        }

        // Guard against recursive calls during fetch
        if ($this->fetchInProgress) {
            return;
        }

        $now = time();

        // Check if cache is still valid
        if ($this->fetched && $this->lastFetchTime !== null) {
            if (($now - $this->lastFetchTime) < $this->ttlSeconds) {
                return;
            }
        }

        $this->fetchInProgress = true;
        try {
            $this->fetchAliases();
            $this->fetched = true;
            $this->lastFetchTime = $now;
        } finally {
            $this->fetchInProgress = false;
        }
    }

    private function fetchAliases(): void
    {
        if ($this->registeredIndices === []) {
            return;
        }

        try {
            // Single batch request for all registered indices
            $indices = implode(',', array_keys($this->registeredIndices));
            $response = $this->client->indices()->getAlias(['index' => $indices]);
            $responseArray = $response->asArray();

            foreach ($responseArray as $realIndex => $data) {
                // Map real index to the first matching registered index
                foreach (array_keys($this->registeredIndices) as $registeredIndex) {
                    // Check if real index matches or is an alias of registered index
                    if ($realIndex === $registeredIndex) {
                        $this->aliasMap[$realIndex] = $registeredIndex;
                        break;
                    }
                }

                // Map all aliases to their registered index
                foreach (array_keys($data['aliases'] ?? []) as $alias) {
                    foreach (array_keys($this->registeredIndices) as $registeredIndex) {
                        if ($alias === $registeredIndex || $realIndex === $registeredIndex) {
                            $this->aliasMap[$alias] = $registeredIndex;
                            $this->aliasMap[$realIndex] = $registeredIndex;
                            break;
                        }
                    }
                }
            }
        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            // 404 - indices don't exist yet, that's okay
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    public function invalidate(): void
    {
        $this->fetched = false;
        $this->lastFetchTime = null;
        $this->aliasMap = [];
        $this->fetchInProgress = false;
    }
}
