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
use Jackardios\EsScoutDriver\Exceptions\NotSearchableModelException;
use Jackardios\EsScoutDriver\Searchable;
use Laravel\Scout\Traits\ConfiguresJobOptions;

/**
 * Deletes the documents with a bulk request to the client of each connection. It carries the index, id, routing and
 * connection of every model instead of the models, so it does not call Engine::delete(): an engine wrapper or another
 * EngineInterface implementation does not see queued deletes. To change them, extend this job or write another and
 * register it with Scout::removeFromSearchUsing().
 */
class RemoveFromSearch implements ShouldQueue
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
            if (!self::hasDriverMethods($model)) {
                throw new NotSearchableModelException($model::class, sprintf(
                    '%s cannot remove %s: the model lacks searchableRouting() or searchableConnection(), which '
                    . '%s provides. For a model of another Scout engine, register a job that handles it with '
                    . 'Scout::removeFromSearchUsing().',
                    self::class,
                    $model::class,
                    Searchable::class,
                ));
            }

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

    /**
     * Scout's own Searchable trait, which a model of another Scout engine uses, has neither method. Kept apart from
     * the loop so that the check does not narrow the type of the model there.
     */
    private static function hasDriverMethods(Model $model): bool
    {
        return method_exists($model, 'searchableRouting') && method_exists($model, 'searchableConnection');
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
