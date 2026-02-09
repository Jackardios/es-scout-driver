<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Author;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class MultiIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        $this->createIndex('authors', [
            'mappings' => [
                'properties' => [
                    'name' => ['type' => 'text'],
                    'email' => ['type' => 'keyword'],
                    'phone_number' => ['type' => 'keyword'],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteIndex('books');
        $this->deleteIndex('authors');
        parent::tearDown();
    }

    #[Test]
    public function test_join_searches_multiple_indices(): void
    {
        // Create books
        $book1 = Book::factory()->create(['title' => 'The Great Gatsby']);
        $book2 = Book::factory()->create(['title' => 'Animal Farm']);

        // Create authors
        $author1 = Author::factory()->create(['name' => 'F. Scott Fitzgerald']);
        $author2 = Author::factory()->create(['name' => 'George Orwell']);

        $book1->searchable();
        $book2->searchable();
        $author1->searchable();
        $author2->searchable();

        $this->refreshIndex('books');
        $this->refreshIndex('authors');

        // Search across both indices
        $result = Book::searchQuery(Query::matchAll())
            ->join(Author::class)
            ->execute();

        // Should return results from both indices
        $this->assertSame(4, $result->total);

        $hits = $result->hits();
        $this->assertCount(4, $hits);

        // Verify we have hits from both indices
        $indexNames = $hits->map(fn($hit) => $hit->indexName)->unique();
        $this->assertCount(2, $indexNames);
        $this->assertContains('books', $indexNames);
        $this->assertContains('authors', $indexNames);
    }

    #[Test]
    public function test_join_with_boost(): void
    {
        // Create books
        $book1 = Book::factory()->create(['title' => 'The Great Gatsby']);
        $book2 = Book::factory()->create(['title' => 'Animal Farm']);

        // Create authors
        $author1 = Author::factory()->create(['name' => 'F. Scott Fitzgerald']);
        $author2 = Author::factory()->create(['name' => 'George Orwell']);

        $book1->searchable();
        $book2->searchable();
        $author1->searchable();
        $author2->searchable();

        $this->refreshIndex('books');
        $this->refreshIndex('authors');

        // Search with boost for authors index
        $builder = Book::searchQuery(Query::matchAll())
            ->join(Author::class, 2.0);

        // Get the query parameters to verify indices_boost is set
        $params = $builder->buildParams();

        // Verify indices_boost is present in the query
        $this->assertArrayHasKey('indices_boost', $params['body']);
        $this->assertIsArray($params['body']['indices_boost']);

        // Execute the query
        $result = $builder->execute();

        $this->assertSame(4, $result->total);

        $hits = $result->hits();
        $this->assertCount(4, $hits);

        // Verify hits from both indices are returned
        $indexNames = $hits->map(fn($hit) => $hit->indexName)->unique();
        $this->assertCount(2, $indexNames);
        $this->assertContains('books', $indexNames);
        $this->assertContains('authors', $indexNames);
    }
}
