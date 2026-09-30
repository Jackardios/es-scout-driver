<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;

/**
 * Maps the concrete index of a hit to the registered index (an index, alias, data stream, pattern or comma list)
 * it was searched through. Each registered name is resolved by Elasticsearch once per registry.
 *
 * @internal
 */
final class AliasRegistry
{
    /** @var array<string, bool> registered index => resolved */
    private array $registeredIndices = [];

    /** @var array<string, string> concrete index => registered index */
    private array $concreteIndices = [];

    public function __construct(
        private readonly ?Client $client = null,
    ) {}

    public function registerIndex(string $indexName): void
    {
        $this->registeredIndices[$indexName] ??= false;
    }

    public function resolve(string $indexName): string
    {
        if (isset($this->registeredIndices[$indexName])) {
            return $indexName;
        }

        $this->resolveRegisteredIndices();

        return $this->concreteIndices[$indexName] ?? $indexName;
    }

    private function resolveRegisteredIndices(): void
    {
        if ($this->client === null) {
            return;
        }

        foreach ($this->registeredIndices as $registeredIndex => $resolved) {
            if ($resolved) {
                continue;
            }

            try {
                /** @var ElasticsearchResponse $response */
                $response = $this->client->indices()->getSettings(['index' => $registeredIndex, 'name' => 'index.uuid']);

                foreach (array_keys($response->asArray()) as $concreteIndex) {
                    $this->concreteIndices[(string) $concreteIndex] ??= $registeredIndex;
                }
            } catch (ClientResponseException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }

            $this->registeredIndices[$registeredIndex] = true;
        }
    }
}
