<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Jackardios\EsScoutDriver\Engine\Engine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EngineConnectionBatchingTest extends TestCase
{
    private Container $container;
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->container = new Container();
        $this->container->instance('config', new ConfigRepository([
            'scout' => [
                'soft_delete' => false,
            ],
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
    public function update_routes_bulk_operations_to_the_matching_connections(): void
    {
        $secondaryClient = new FakeHttpClient();
        $archiveClient = new FakeHttpClient();
        $this->container->instance('elastic.client.connection.secondary', $secondaryClient->client());
        $this->container->instance('elastic.client.connection.archive', $archiveClient->client());

        $engine = $this->createEngine();

        $model1 = $this->createModel('1', 'books', ['title' => 'Book One'], connection: 'secondary');
        $model2 = $this->createModel('2', 'authors', ['name' => 'Author Two'], connection: 'secondary');
        $model3 = $this->createModel('3', 'books', ['title' => 'Book Three'], connection: 'archive', routing: 'tenant-a');

        $engine->update(new Collection([$model1, $model2, $model3]));

        $this->assertCount(1, $secondaryClient->requests);
        $this->assertSame([
            ['index' => ['_index' => 'books', '_id' => '1']],
            ['title' => 'Book One'],
            ['index' => ['_index' => 'authors', '_id' => '2']],
            ['name' => 'Author Two'],
        ], $secondaryClient->bulkLines());
        $this->assertSame('', $secondaryClient->requests[0]->getUri()->getQuery());

        $this->assertCount(1, $archiveClient->requests);
        $this->assertSame([
            ['index' => ['_index' => 'books', '_id' => '3', 'routing' => 'tenant-a']],
            ['title' => 'Book Three'],
        ], $archiveClient->bulkLines());
        $this->assertSame('', $archiveClient->requests[0]->getUri()->getQuery());
    }

    #[Test]
    public function delete_routes_bulk_operations_to_the_matching_connections(): void
    {
        $secondaryClient = new FakeHttpClient();
        $archiveClient = new FakeHttpClient();
        $this->container->instance('elastic.client.connection.secondary', $secondaryClient->client());
        $this->container->instance('elastic.client.connection.archive', $archiveClient->client());

        $engine = $this->createEngine();

        $model1 = $this->createModel('1', 'books', ['title' => 'Book One'], connection: 'secondary', routing: 'tenant-a');
        $model2 = $this->createModel('2', 'books', ['title' => 'Book Two'], connection: 'secondary');
        $model3 = $this->createModel('3', 'books', ['title' => 'Book Three'], connection: 'archive');

        $engine->delete(new Collection([$model1, $model2, $model3]));

        $this->assertCount(1, $secondaryClient->requests);
        $this->assertSame([
            ['delete' => ['_index' => 'books', '_id' => '1', 'routing' => 'tenant-a']],
            ['delete' => ['_index' => 'books', '_id' => '2']],
        ], $secondaryClient->bulkLines());
        $this->assertSame('', $secondaryClient->requests[0]->getUri()->getQuery());

        $this->assertCount(1, $archiveClient->requests);
        $this->assertSame([
            ['delete' => ['_index' => 'books', '_id' => '3']],
        ], $archiveClient->bulkLines());
        $this->assertSame('', $archiveClient->requests[0]->getUri()->getQuery());
    }

    private function createEngine(): Engine
    {
        return new Engine((new FakeHttpClient())->client());
    }

    /**
     * @param array<string, mixed> $searchableData
     * @param array<int, string>|string|null $searchableWith
     */
    private function createModel(
        string $id,
        string $indexName,
        array $searchableData,
        ?string $connection = null,
        ?string $routing = null,
        array|string|null $searchableWith = null,
    ): Model {
        return new class ($id, $indexName, $searchableData, $connection, $routing, $searchableWith) extends Model {
            private string $scoutId;
            private string $scoutIndex;
            private array $searchableData;
            private ?string $scoutConnection;
            private ?string $scoutRouting;
            private array|string|null $scoutWith;

            /**
             * @param array<string, mixed> $searchableData
             * @param array<int, string>|string|null $searchableWith
             */
            public function __construct(
                string $id = '',
                string $indexName = 'books',
                array $searchableData = [],
                ?string $connection = null,
                ?string $routing = null,
                array|string|null $searchableWith = null,
            ) {
                parent::__construct();

                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
                $this->searchableData = $searchableData;
                $this->scoutConnection = $connection;
                $this->scoutRouting = $routing;
                $this->scoutWith = $searchableWith;
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
                return $this->scoutConnection;
            }

            /**
             * @return array<int, string>|string|null
             */
            public function searchableWith(): array|string|null
            {
                return $this->scoutWith;
            }

            public function indexableAs(): string
            {
                return $this->scoutIndex;
            }

            /**
             * @return array<string, mixed>
             */
            public function toSearchableArray(): array
            {
                return $this->searchableData;
            }

            /**
             * @return array<string, mixed>
             */
            public function scoutMetadata(): array
            {
                return [];
            }

        };
    }
}
