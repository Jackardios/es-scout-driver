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

    public function __construct(Collection $models)
    {
        if ($models->isEmpty()) {
            throw new InvalidArgumentException('Cannot create RemoveFromSearch job with empty collection');
        }

        $this->indexName = $models->first()->searchableAs();

        $this->documentIds = $models->map(
            static fn(Model $model) => (string) $model->getScoutKey()
        )->all();

        $this->routing = [];
        /** @var Model $model */
        foreach ($models as $model) {
            if (method_exists($model, 'searchableRouting') && $model->searchableRouting() !== null) {
                $this->routing[(string) $model->getScoutKey()] = (string) $model->searchableRouting();
            }
        }
    }

    public function handle(Client $client): void
    {
        $refreshDocuments = (bool) config('elastic.scout.refresh_documents', false);

        $body = [];

        foreach ($this->documentIds as $documentId) {
            $metadata = [
                '_index' => $this->indexName,
                '_id' => $documentId,
            ];

            if (isset($this->routing[$documentId])) {
                $metadata['routing'] = $this->routing[$documentId];
            }

            $body[] = ['delete' => $metadata];
        }

        $params = ['body' => $body];

        if ($refreshDocuments) {
            $params['refresh'] = 'true';
        }

        $response = $client->bulk($params);
        $this->handleBulkResponse($response->asArray());
    }
}
