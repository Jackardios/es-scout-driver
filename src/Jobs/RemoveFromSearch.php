<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Engine\HandlesBulkResponse;

final class RemoveFromSearch implements ShouldQueue
{
    use Queueable;
    use HandlesBulkResponse;

    public string $indexName;

    /** @var array<string, string> document ID => routing value */
    public array $routing;

    /** @var array<int, string> */
    public array $documentIds;

    /**
     * @var array<int, array{
     *     connection: string|null,
     *     index: string,
     *     id: string,
     *     routing: string|null
     * }>
     */
    public array $operations;

    public function __construct(Collection $models)
    {
        if ($models->isEmpty()) {
            throw new InvalidArgumentException('Cannot create RemoveFromSearch job with empty collection');
        }

        $this->indexName = $models->first()->searchableAs();
        $this->operations = [];

        $this->documentIds = $models->map(
            static fn(Model $model) => (string) $model->getScoutKey()
        )->all();

        $this->routing = [];
        /** @var Model $model */
        foreach ($models as $model) {
            $documentId = (string) $model->getScoutKey();
            $routing = null;

            if (method_exists($model, 'searchableRouting')) {
                $resolvedRouting = $model->searchableRouting();

                if ($resolvedRouting !== null) {
                    $routing = (string) $resolvedRouting;
                    $this->routing[$documentId] = $routing;
                }
            }

            $connection = method_exists($model, 'searchableConnection')
                ? $model->searchableConnection()
                : null;

            $this->operations[] = [
                'connection' => $connection !== null ? (string) $connection : null,
                'index' => $model->searchableAs(),
                'id' => $documentId,
                'routing' => $routing,
            ];
        }
    }

    public function handle(Client $client): void
    {
        $refreshDocuments = (bool) config('elastic.scout.refresh_documents', false);
        $operations = $this->operations !== []
            ? $this->operations
            : array_map(
                fn(string $documentId) => [
                    'connection' => null,
                    'index' => $this->indexName,
                    'id' => $documentId,
                    'routing' => $this->routing[$documentId] ?? null,
                ],
                $this->documentIds,
            );

        $operationsByConnection = [];
        foreach ($operations as $operation) {
            $key = $operation['connection'] ?? '__default__';
            $operationsByConnection[$key][] = $operation;
        }

        foreach ($operationsByConnection as $connection => $connectionOperations) {
            $resolvedClient = $connection === '__default__'
                ? $client
                : app("elastic.client.connection.$connection");

            $body = [];
            foreach ($connectionOperations as $operation) {
                $metadata = [
                    '_index' => $operation['index'],
                    '_id' => $operation['id'],
                ];

                if ($operation['routing'] !== null) {
                    $metadata['routing'] = $operation['routing'];
                }

                $body[] = ['delete' => $metadata];
            }

            $params = ['body' => $body];
            if ($refreshDocuments) {
                $params['refresh'] = 'true';
            }

            $response = $resolvedClient->bulk($params);
            $this->handleBulkResponse($response->asArray());
        }
    }
}
