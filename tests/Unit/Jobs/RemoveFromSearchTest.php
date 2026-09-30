<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Jobs;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Exceptions\BulkOperationException;
use Jackardios\EsScoutDriver\Jobs\RemoveFromSearch;
use Jackardios\EsScoutDriver\Tests\Unit\Engine\FakeHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoveFromSearchTest extends TestCase
{
    private Container $container;
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->container = new Container();
        $this->container->instance('config', new ConfigRepository([
            'elastic' => [
                'scout' => [
                    'refresh_documents' => false,
                ],
            ],
        ]));
        Container::setInstance($this->container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    #[Test]
    public function it_throws_exception_for_empty_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create RemoveFromSearch job with empty collection.');

        new RemoveFromSearch(new Collection());
    }

    #[Test]
    public function it_extracts_operations_from_models(): void
    {
        $model1 = $this->createModelWithScout('1', 'books');
        $model2 = $this->createModelWithRouting('2', 'books', 'route-a');

        $job = new RemoveFromSearch(new Collection([$model1, $model2]));

        $this->assertSame([
            [
                'connection' => null,
                'index' => 'books',
                'id' => '1',
                'routing' => null,
            ],
            [
                'connection' => null,
                'index' => 'books',
                'id' => '2',
                'routing' => 'route-a',
            ],
        ], $job->operations);
    }

    #[Test]
    public function it_converts_scout_key_and_routing_to_strings_in_operations(): void
    {
        $model = $this->createModelWithIntegerRouting(42, 'books', 123);

        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame([
            [
                'connection' => null,
                'index' => 'books',
                'id' => '42',
                'routing' => '123',
            ],
        ], $job->operations);
    }

    #[Test]
    public function it_keeps_per_model_indices_in_operations(): void
    {
        $model1 = $this->createModelWithScout('1', 'books');
        $model2 = $this->createModelWithScout('2', 'authors');

        $job = new RemoveFromSearch(new Collection([$model1, $model2]));

        $this->assertSame([
            [
                'connection' => null,
                'index' => 'books',
                'id' => '1',
                'routing' => null,
            ],
            [
                'connection' => null,
                'index' => 'authors',
                'id' => '2',
                'routing' => null,
            ],
        ], $job->operations);
    }

    #[Test]
    public function it_extracts_connection_for_each_operation(): void
    {
        $model = $this->createModelWithConnection('1', 'books', 'analytics');

        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame([
            [
                'connection' => 'analytics',
                'index' => 'books',
                'id' => '1',
                'routing' => null,
            ],
        ], $job->operations);
    }

    #[Test]
    public function it_takes_the_job_options_from_scout_config(): void
    {
        /** @var ConfigRepository $config */
        $config = $this->container->make('config');
        $config->set('scout.jobs', ['tries' => 5, 'backoff' => 7, 'max_exceptions' => 2]);

        $job = new RemoveFromSearch(new Collection([$this->createModelWithScout('1', 'books')]));

        $this->assertSame(5, $job->tries);
        $this->assertSame(7, $job->backoff);
        $this->assertSame(2, $job->maxExceptions);
    }

    #[Test]
    public function handle_routes_operations_to_named_connections_in_separate_batches(): void
    {
        $model1 = $this->createModelWithConnection('1', 'books', 'secondary');
        $model2 = $this->createModelWithConnection('2', 'books', 'secondary');
        $model3 = $this->createModelWithConnection('3', 'books', 'archive');
        $model4 = $this->createModelWithScout('4', 'books');
        $job = new RemoveFromSearch(new Collection([$model1, $model2, $model3, $model4]));

        $defaultHttp = new FakeHttpClient();
        $secondaryHttp = new FakeHttpClient();
        $archiveHttp = new FakeHttpClient();
        $this->container->instance('elastic.client.connection.secondary', $secondaryHttp->client());
        $this->container->instance('elastic.client.connection.archive', $archiveHttp->client());

        $job->handle($defaultHttp->client());

        $this->assertSame([
            ['delete' => ['_index' => 'books', '_id' => '1']],
            ['delete' => ['_index' => 'books', '_id' => '2']],
        ], $secondaryHttp->bulkLines());
        $this->assertSame([['delete' => ['_index' => 'books', '_id' => '3']]], $archiveHttp->bulkLines());
        $this->assertSame([['delete' => ['_index' => 'books', '_id' => '4']]], $defaultHttp->bulkLines());
        $this->assertSame('', $secondaryHttp->requests[0]->getUri()->getQuery());
    }

    #[Test]
    public function handle_includes_refresh_and_routing_for_named_connections_when_enabled(): void
    {
        $this->setRefreshDocuments(true);

        $model = $this->createModelWithRoutingAndConnection('1', 'books', 'tenant-7', 'secondary');
        $job = new RemoveFromSearch(new Collection([$model]));

        $secondaryHttp = new FakeHttpClient();
        $this->container->instance('elastic.client.connection.secondary', $secondaryHttp->client());

        $job->handle((new FakeHttpClient())->client());

        $this->assertSame([
            ['delete' => ['_index' => 'books', '_id' => '1', 'routing' => 'tenant-7']],
        ], $secondaryHttp->bulkLines());
        $this->assertSame('refresh=true', $secondaryHttp->requests[0]->getUri()->getQuery());
    }

    #[Test]
    public function handle_throws_when_a_delete_fails(): void
    {
        $job = new RemoveFromSearch(new Collection([$this->createModelWithScout('1', 'books')]));
        $http = new FakeHttpClient([[200, [
            'errors' => true,
            'items' => [['delete' => ['_id' => '1', '_index' => 'books', 'error' => ['type' => 'error', 'reason' => 'Failed']]]],
        ]]]);

        $this->expectException(BulkOperationException::class);

        $job->handle($http->client());
    }

    #[Test]
    public function job_properties_are_public(): void
    {
        $model = $this->createModelWithRouting('1', 'books', 'route-a');
        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame([
            [
                'connection' => null,
                'index' => 'books',
                'id' => '1',
                'routing' => 'route-a',
            ],
        ], $job->operations);
    }

    private function setRefreshDocuments(bool $enabled): void
    {
        /** @var ConfigRepository $config */
        $config = $this->container->make('config');
        $config->set('elastic.scout.refresh_documents', $enabled);
    }

    private function createModelWithScout(string $id, string $indexName): Model
    {
        return new class ($id, $indexName) extends Model {
            private string $scoutId;
            private string $scoutIndex;

            public function __construct(string $id, string $indexName)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
            }

            public function getScoutKey(): string
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }

            public function indexableAs(): string
            {
                return $this->scoutIndex;
            }

            public function searchableRouting(): ?string
            {
                return null;
            }

            public function searchableConnection(): ?string
            {
                return null;
            }
        };
    }

    private function createModelWithRouting(string $id, string $indexName, ?string $routing): Model
    {
        return new class ($id, $indexName, $routing) extends Model {
            private string $scoutId;
            private string $scoutIndex;
            private ?string $scoutRouting;

            public function __construct(string $id, string $indexName, ?string $routing)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
                $this->scoutRouting = $routing;
            }

            public function getScoutKey(): string
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }

            public function indexableAs(): string
            {
                return $this->scoutIndex;
            }

            public function searchableRouting(): ?string
            {
                return $this->scoutRouting;
            }

            public function searchableConnection(): ?string
            {
                return null;
            }
        };
    }

    private function createModelWithIntegerRouting(int $id, string $indexName, int $routing): Model
    {
        return new class ($id, $indexName, $routing) extends Model {
            private int $scoutId;
            private string $scoutIndex;
            private int $scoutRouting;

            public function __construct(int $id, string $indexName, int $routing)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
                $this->scoutRouting = $routing;
            }

            public function getScoutKey(): int
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }

            public function indexableAs(): string
            {
                return $this->scoutIndex;
            }

            public function searchableRouting(): int
            {
                return $this->scoutRouting;
            }

            public function searchableConnection(): ?string
            {
                return null;
            }
        };
    }

    private function createModelWithConnection(string $id, string $indexName, string $connection): Model
    {
        return new class ($id, $indexName, $connection) extends Model {
            private string $scoutId;
            private string $scoutIndex;
            private string $scoutConnection;

            public function __construct(string $id, string $indexName, string $connection)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
                $this->scoutConnection = $connection;
            }

            public function getScoutKey(): string
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }

            public function indexableAs(): string
            {
                return $this->scoutIndex;
            }

            public function searchableRouting(): ?string
            {
                return null;
            }

            public function searchableConnection(): string
            {
                return $this->scoutConnection;
            }
        };
    }

    private function createModelWithRoutingAndConnection(string $id, string $indexName, string $routing, string $connection): Model
    {
        return new class ($id, $indexName, $routing, $connection) extends Model {
            private string $scoutId;
            private string $scoutIndex;
            private string $scoutRouting;
            private string $scoutConnection;

            public function __construct(string $id, string $indexName, string $routing, string $connection)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
                $this->scoutRouting = $routing;
                $this->scoutConnection = $connection;
            }

            public function getScoutKey(): string
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }

            public function indexableAs(): string
            {
                return $this->scoutIndex;
            }

            public function searchableRouting(): string
            {
                return $this->scoutRouting;
            }

            public function searchableConnection(): string
            {
                return $this->scoutConnection;
            }
        };
    }
}
