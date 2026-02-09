<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Engine\Engine;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class EngineTest extends TestCase
{
    protected Engine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(Engine::class);
        $this->createIndex('books', [
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text'],
                    'author' => ['type' => 'keyword'],
                    'price' => ['type' => 'float'],
                    'description' => ['type' => 'text'],
                    'tags' => ['type' => 'keyword'],
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
    public function test_engine_updates_documents(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book One']);
        $book2 = Book::factory()->create(['title' => 'Book Two']);

        $this->engine->update(collect([$book1, $book2]));
        $this->refreshIndex('books');

        // Verify docs exist in ES via raw search
        $response = $this->client->search([
            'index' => 'books',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ]);

        $hits = $response['hits']['hits'];
        $this->assertCount(2, $hits);

        $titles = array_column(array_column($hits, '_source'), 'title');
        $this->assertContains('Book One', $titles);
        $this->assertContains('Book Two', $titles);
    }

    #[Test]
    public function test_engine_deletes_documents(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book One']);
        $book2 = Book::factory()->create(['title' => 'Book Two']);

        $this->engine->update(collect([$book1, $book2]));
        $this->refreshIndex('books');

        // Delete book1
        $this->engine->delete(collect([$book1]));
        $this->refreshIndex('books');

        // Verify only book2 remains
        $response = $this->client->search([
            'index' => 'books',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ]);

        $hits = $response['hits']['hits'];
        $this->assertCount(1, $hits);
        $this->assertSame('Book Two', $hits[0]['_source']['title']);
    }

    #[Test]
    public function test_engine_flush_removes_all(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book One']);
        $book2 = Book::factory()->create(['title' => 'Book Two']);

        $this->engine->update(collect([$book1, $book2]));
        $this->refreshIndex('books');

        // Flush all books
        $this->engine->flush(new Book());
        $this->refreshIndex('books');

        // Verify 0 results
        $response = $this->client->search([
            'index' => 'books',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ]);

        $this->assertSame(0, $response['hits']['total']['value']);
    }

    #[Test]
    public function test_engine_create_and_delete_index(): void
    {
        $indexName = 'test_idx';

        // Create index
        $this->engine->createIndex($indexName, []);
        $this->assertTrue($this->indexExists($indexName));

        // Delete index
        $this->engine->deleteIndex($indexName);
        $this->assertFalse($this->indexExists($indexName));
    }
}
