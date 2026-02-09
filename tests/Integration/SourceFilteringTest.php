<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class SourceFilteringTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->deleteIndex('books');
        parent::tearDown();
    }

    #[Test]
    public function test_source_includes(): void
    {
        $book = Book::factory()->create([
            'title' => 'The Great Gatsby',
            'author' => 'F. Scott Fitzgerald',
            'price' => 19.99,
            'description' => 'A classic American novel',
            'tags' => ['classic', 'fiction'],
        ]);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->source(['title', 'price'])
            ->execute();

        $this->assertSame(1, $result->total);

        $documents = $result->documents();
        $this->assertCount(1, $documents);

        $document = $documents->first();
        $this->assertArrayHasKey('title', $document);
        $this->assertArrayHasKey('price', $document);
        $this->assertArrayNotHasKey('author', $document);
        $this->assertArrayNotHasKey('description', $document);
        $this->assertArrayNotHasKey('tags', $document);

        $this->assertSame('The Great Gatsby', $document['title']);
        $this->assertSame(19.99, $document['price']);
    }

    #[Test]
    public function test_source_with_excludes(): void
    {
        $book = Book::factory()->create([
            'title' => 'Elasticsearch Guide',
            'author' => 'John Doe',
            'price' => 29.99,
            'description' => 'A comprehensive guide to Elasticsearch',
            'tags' => ['technical', 'search'],
        ]);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->source(['*'], ['description', 'tags'])
            ->execute();

        $this->assertSame(1, $result->total);

        $documents = $result->documents();
        $this->assertCount(1, $documents);

        $document = $documents->first();
        $this->assertArrayHasKey('title', $document);
        $this->assertArrayHasKey('author', $document);
        $this->assertArrayHasKey('price', $document);
        $this->assertArrayNotHasKey('description', $document);
        $this->assertArrayNotHasKey('tags', $document);
    }

    #[Test]
    public function test_source_disabled(): void
    {
        $book = Book::factory()->create([
            'title' => 'Sample Book',
            'author' => 'Jane Doe',
            'price' => 15.99,
            'description' => 'A sample book',
        ]);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->sourceRaw(false)
            ->execute();

        $this->assertSame(1, $result->total);

        $documents = $result->documents();
        $this->assertCount(1, $documents);

        $document = $documents->first();

        // When source is disabled, document should be empty or not contain the fields
        $this->assertEmpty($document);
    }

    #[Test]
    public function test_source_single_field(): void
    {
        $book = Book::factory()->create([
            'title' => 'Programming Book',
            'author' => 'Developer Author',
            'price' => 49.99,
            'description' => 'Learn programming',
        ]);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->source(['author'])
            ->execute();

        $this->assertSame(1, $result->total);

        $documents = $result->documents();
        $document = $documents->first();

        $this->assertArrayHasKey('author', $document);
        $this->assertArrayNotHasKey('title', $document);
        $this->assertArrayNotHasKey('price', $document);
        $this->assertArrayNotHasKey('description', $document);

        $this->assertSame('Developer Author', $document['author']);
    }

    #[Test]
    public function test_source_wildcard_pattern(): void
    {
        $book = Book::factory()->create([
            'title' => 'Data Science Book',
            'author' => 'Data Scientist',
            'price' => 59.99,
            'description' => 'Advanced data science techniques',
            'tags' => ['data', 'science', 'ml'],
        ]);
        $book->searchable();

        $this->refreshIndex('books');

        // Include only fields starting with 't'
        $result = Book::searchQuery(Query::matchAll())
            ->source(['t*'])
            ->execute();

        $this->assertSame(1, $result->total);

        $documents = $result->documents();
        $document = $documents->first();

        $this->assertArrayHasKey('title', $document);
        $this->assertArrayHasKey('tags', $document);
        $this->assertArrayNotHasKey('author', $document);
        $this->assertArrayNotHasKey('price', $document);
        $this->assertArrayNotHasKey('description', $document);
    }
}
