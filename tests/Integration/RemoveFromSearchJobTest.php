<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Jackardios\EsScoutDriver\Jobs\RemoveFromSearch;
use PHPUnit\Framework\Attributes\Test;

final class RemoveFromSearchJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createIndex('books', [
            'mappings' => [
                'properties' => [
                    'author' => ['type' => 'keyword'],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteIndex('books');
        parent::tearDown();
    }

    #[Test]
    public function handle_deletes_documents_using_operations_payload(): void
    {
        $this->client->index([
            'index' => 'books',
            'id' => '1',
            'routing' => 'tenant-a',
            'body' => ['author' => 'John Doe'],
        ]);

        $this->client->index([
            'index' => 'books',
            'id' => '2',
            'body' => ['author' => 'Jane Smith'],
        ]);

        $this->refreshIndex('books');

        $countBefore = $this->client->count([
            'index' => 'books',
            'body' => [
                'query' => ['match_all' => new \stdClass()],
            ],
        ]);
        $this->assertSame(2, $countBefore['count']);

        $job = new RemoveFromSearch(new Collection([
            $this->createModelWithRouting('1', 'books', 'tenant-a'),
            $this->createModel('2', 'books'),
        ]));

        $job->handle($this->client);
        $this->refreshIndex('books');

        $countAfter = $this->client->count([
            'index' => 'books',
            'body' => [
                'query' => ['match_all' => new \stdClass()],
            ],
        ]);

        $this->assertSame(0, $countAfter['count']);
    }

    private function createModel(string $id, string $indexName): Model
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

    private function createModelWithRouting(string $id, string $indexName, string $routing): Model
    {
        return new class ($id, $indexName, $routing) extends Model {
            private string $scoutId;
            private string $scoutIndex;
            private string $scoutRouting;

            public function __construct(string $id, string $indexName, string $routing)
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

            public function searchableRouting(): string
            {
                return $this->scoutRouting;
            }

            public function searchableConnection(): ?string
            {
                return null;
            }
        };
    }
}
