<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Jobs;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Engine\ConnectionOperationRouter;
use Jackardios\EsScoutDriver\Engine\HandlesBulkResponse;

final class RemoveFromSearch implements ShouldQueue
{
    use Queueable;
    use HandlesBulkResponse;

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

        $this->operations = [];

        /** @var Model $model */
        foreach ($models as $model) {
            $documentId = (string) $model->getScoutKey();
            $resolvedRouting = $model->searchableRouting();
            $routing = $resolvedRouting !== null ? (string) $resolvedRouting : null;

            $connection = $model->searchableConnection();

            $this->operations[] = [
                'connection' => $connection !== null ? (string) $connection : null,
                'index' => $model->indexableAs(),
                'id' => $documentId,
                'routing' => $routing,
            ];
        }
    }

    public function handle(Client $client): void
    {
        $refreshDocuments = (bool) config('elastic.scout.refresh_documents', false);
        if ($this->operations === []) {
            throw new InvalidArgumentException('RemoveFromSearch job payload must contain operations.');
        }

        $connectionRouter = new ConnectionOperationRouter();
        $operationsByConnection = $connectionRouter->groupByConnection(
            $this->operations,
            static fn(array $operation): ?string => is_string($operation['connection'] ?? null)
                ? $operation['connection']
                : null,
        );

        foreach ($operationsByConnection as $connection => $connectionOperations) {
            $resolvedClient = $connectionRouter->resolveClientForConnection($connection, $client);

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

            /** @var ElasticsearchResponse $response */
            $response = $resolvedClient->bulk($params);
            $this->handleBulkResponse($response->asArray());
        }
    }
}
