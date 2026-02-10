<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Jobs;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Exceptions\BulkOperationException;
use Jackardios\EsScoutDriver\Jobs\RemoveFromSearch;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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
        $this->expectExceptionMessage('Cannot create RemoveFromSearch job with empty collection');

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
    public function handle_routes_operations_to_named_connections_in_separate_batches(): void
    {
        $model1 = $this->createModelWithConnection('1', 'books', 'secondary');
        $model2 = $this->createModelWithConnection('2', 'books', 'secondary');
        $model3 = $this->createModelWithConnection('3', 'books', 'archive');
        $job = new RemoveFromSearch(new Collection([$model1, $model2, $model3]));

        $secondaryClient = new RemoveFromSearchConnectionSpy();
        $archiveClient = new RemoveFromSearchConnectionSpy();
        $this->container->instance('elastic.client.connection.secondary', $secondaryClient);
        $this->container->instance('elastic.client.connection.archive', $archiveClient);

        $job->handle($this->createClient());

        $this->assertCount(1, $secondaryClient->bulkCalls);
        $this->assertSame([
            ['delete' => ['_index' => 'books', '_id' => '1']],
            ['delete' => ['_index' => 'books', '_id' => '2']],
        ], $secondaryClient->bulkCalls[0]['body']);
        $this->assertArrayNotHasKey('refresh', $secondaryClient->bulkCalls[0]);

        $this->assertCount(1, $archiveClient->bulkCalls);
        $this->assertSame([
            ['delete' => ['_index' => 'books', '_id' => '3']],
        ], $archiveClient->bulkCalls[0]['body']);
        $this->assertArrayNotHasKey('refresh', $archiveClient->bulkCalls[0]);
    }

    #[Test]
    public function handle_includes_refresh_and_routing_for_named_connections_when_enabled(): void
    {
        $this->setRefreshDocuments(true);

        $model = $this->createModelWithRoutingAndConnection('1', 'books', 'tenant-7', 'secondary');
        $job = new RemoveFromSearch(new Collection([$model]));

        $secondaryClient = new RemoveFromSearchConnectionSpy();
        $this->container->instance('elastic.client.connection.secondary', $secondaryClient);

        $job->handle($this->createClient());

        $this->assertCount(1, $secondaryClient->bulkCalls);
        $this->assertSame([
            ['delete' => ['_index' => 'books', '_id' => '1', 'routing' => 'tenant-7']],
        ], $secondaryClient->bulkCalls[0]['body']);
        $this->assertSame('true', $secondaryClient->bulkCalls[0]['refresh']);
    }

    #[Test]
    public function handle_throws_when_operations_payload_is_empty(): void
    {
        $job = new RemoveFromSearch(new Collection([$this->createModelWithScout('1', 'books')]));
        $job->operations = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RemoveFromSearch job payload must contain operations.');

        $job->handle($this->createClient());
    }

    #[Test]
    public function handle_bulk_response_does_not_throw_when_no_errors(): void
    {
        $model = $this->createModelWithScout('1', 'books');
        $job = new RemoveFromSearch(new Collection([$model]));

        $response = ['errors' => false];

        $this->invokeHandleBulkResponse($job, $response);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function handle_bulk_response_does_not_throw_when_errors_key_missing(): void
    {
        $model = $this->createModelWithScout('1', 'books');
        $job = new RemoveFromSearch(new Collection([$model]));

        $response = [];

        $this->invokeHandleBulkResponse($job, $response);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function handle_bulk_response_throws_bulk_operation_exception_on_errors(): void
    {
        $model = $this->createModelWithScout('1', 'books');
        $job = new RemoveFromSearch(new Collection([$model]));

        $response = [
            'errors' => true,
            'items' => [
                [
                    'delete' => [
                        '_id' => '1',
                        '_index' => 'books',
                        'error' => ['type' => 'not_found', 'reason' => 'Document not found'],
                    ],
                ],
            ],
        ];

        $this->expectException(BulkOperationException::class);

        $this->invokeHandleBulkResponse($job, $response);
    }

    #[Test]
    public function handle_bulk_response_collects_all_failed_documents(): void
    {
        $model1 = $this->createModelWithScout('1', 'books');
        $model2 = $this->createModelWithScout('2', 'books');
        $job = new RemoveFromSearch(new Collection([$model1, $model2]));

        $response = [
            'errors' => true,
            'items' => [
                [
                    'delete' => [
                        '_id' => '1',
                        '_index' => 'books',
                        'error' => ['type' => 'error1', 'reason' => 'Reason 1'],
                    ],
                ],
                [
                    'delete' => [
                        '_id' => '2',
                        '_index' => 'books',
                        'error' => ['type' => 'error2', 'reason' => 'Reason 2'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($job, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $failedDocs = $e->getFailedDocuments();
            $this->assertCount(2, $failedDocs);
            $this->assertSame('1', $failedDocs[0]['id']);
            $this->assertSame('2', $failedDocs[1]['id']);
        }
    }

    #[Test]
    public function handle_bulk_response_ignores_successful_items(): void
    {
        $model1 = $this->createModelWithScout('1', 'books');
        $model2 = $this->createModelWithScout('2', 'books');
        $job = new RemoveFromSearch(new Collection([$model1, $model2]));

        $response = [
            'errors' => true,
            'items' => [
                [
                    'delete' => [
                        '_id' => '1',
                        '_index' => 'books',
                        'result' => 'deleted',
                    ],
                ],
                [
                    'delete' => [
                        '_id' => '2',
                        '_index' => 'books',
                        'error' => ['type' => 'error', 'reason' => 'Failed'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($job, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $failedDocs = $e->getFailedDocuments();
            $this->assertCount(1, $failedDocs);
            $this->assertSame('2', $failedDocs[0]['id']);
        }
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

    private function invokeHandleBulkResponse(RemoveFromSearch $job, array $response): void
    {
        $method = new ReflectionMethod(RemoveFromSearch::class, 'handleBulkResponse');
        $method->invoke($job, $response);
    }

    private function setRefreshDocuments(bool $enabled): void
    {
        /** @var ConfigRepository $config */
        $config = $this->container->make('config');
        $config->set('elastic.scout.refresh_documents', $enabled);
    }

    private function createClient(): Client
    {
        return ClientBuilder::create()
            ->setHosts(['http://localhost:9200'])
            ->build();
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

final class RemoveFromSearchConnectionSpy
{
    /** @var array<int, array<string, mixed>> */
    public array $bulkCalls = [];

    /**
     * @param array<string, mixed> $params
     */
    public function bulk(array $params): object
    {
        $this->bulkCalls[] = $params;

        return new class {
            public function asArray(): array
            {
                return ['errors' => false];
            }
        };
    }
}
