<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class TermLevelQueryTest extends TestCase
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
    public function test_prefix_query(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Smith']);
        $book2 = Book::factory()->create(['author' => 'Alice Johnson']);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::prefix('author', 'John'))
            ->execute();

        $this->assertSame(1, $result->total);
        $this->assertSame($book1->id, $result->models()->first()->id);
    }

    #[Test]
    public function test_wildcard_query(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Smith']);
        $book2 = Book::factory()->create(['author' => 'Jane Doe']);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::wildcard('author', 'J*n*'))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
    }

    #[Test]
    public function test_exists_query(): void
    {
        $book1 = Book::factory()->create(['description' => 'A great book']);
        $book2 = Book::factory()->create(['description' => null]);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::exists('description'))
            ->execute();

        $this->assertSame(1, $result->total);
        $this->assertSame($book1->id, $result->models()->first()->id);
    }

    #[Test]
    public function test_ids_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book One']);
        $book2 = Book::factory()->create(['title' => 'Book Two']);
        $book3 = Book::factory()->create(['title' => 'Book Three']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();
        $this->refreshIndex('books');

        // Search by ES document IDs (which are the model keys)
        $result = Book::searchQuery(Query::ids([(string) $book1->id, (string) $book2->id]))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertNotContains($book3->id, $ids);
    }

    #[Test]
    public function test_regexp_query(): void
    {
        $book1 = Book::factory()->create(['author' => 'Smith']);
        $book2 = Book::factory()->create(['author' => 'Smithson']);
        $book3 = Book::factory()->create(['author' => 'Anderson']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::regexp('author', 'Smith.*'))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertNotContains($book3->id, $ids);
    }
}
