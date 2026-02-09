<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class TermQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createIndex('books', [
            'mappings' => [
                'properties' => [
                    'author' => ['type' => 'keyword'],
                    'title' => ['type' => 'text'],
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
    public function test_term_query_filters_by_exact_value(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe', 'title' => 'First Book']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith', 'title' => 'Second Book']);
        $book3 = Book::factory()->create(['author' => 'John Doe', 'title' => 'Third Book']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::term('author', 'John Doe'))
            ->execute();

        $this->assertSame(2, $result->total);

        $models = $result->models();
        $this->assertCount(2, $models);

        $ids = $models->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book3->id, $ids);
        $this->assertNotContains($book2->id, $ids);
    }

    #[Test]
    public function test_terms_query_filters_by_multiple_values(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'Bob Johnson']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::terms('author', ['John Doe', 'Jane Smith']))
            ->execute();

        $this->assertSame(2, $result->total);

        $models = $result->models();
        $this->assertCount(2, $models);

        $ids = $models->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertNotContains($book3->id, $ids);
    }
}
