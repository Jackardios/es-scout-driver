<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Jobs;

use Elastic\Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Engine\ConnectionOperationRouter;
use Jackardios\EsScoutDriver\Engine\SendsBulkRequests;
use Laravel\Scout\Traits\ConfiguresJobOptions;

final class RemoveFromSearch implements ShouldQueue
{
    use ConfiguresJobOptions;
    use Queueable;
    use SendsBulkRequests;

    /**
     * @var array<int, array{
     *     connection: string|null,
     *     index: string,
     *     id: string,
     *     routing: string|null
     * }>
     */
    public array $operations = [];

    public function __construct(Collection $models)
    {
        if ($models->isEmpty()) {
            throw new InvalidArgumentException('Cannot create RemoveFromSearch job with empty collection.');
        }

        /** @var Model $model */
        foreach ($models as $model) {
            $routing = $model->searchableRouting();

            $this->operations[] = [
                'connection' => $model->searchableConnection(),
                'index' => $model->indexableAs(),
                'id' => (string) $model->getScoutKey(),
                'routing' => $routing !== null ? (string) $routing : null,
            ];
        }

        $this->configureJob();
    }

    public function handle(Client $client): void
    {
        $router = new ConnectionOperationRouter();
        $refreshDocuments = (bool) config('elastic.scout.refresh_documents', false);
        $operationsByConnection = $router->groupByConnection(
            $this->operations,
            static fn(array $operation): ?string => $operation['connection'],
        );

        foreach ($operationsByConnection as $connection => $operations) {
            $this->sendBulk(
                $router->resolveClientForConnection($connection, $client),
                array_map(static fn(array $operation): array => ['delete' => array_filter(
                    ['_index' => $operation['index'], '_id' => $operation['id'], 'routing' => $operation['routing']],
                    static fn(?string $value): bool => $value !== null,
                )], $operations),
                $refreshDocuments,
            );
        }
    }
}
