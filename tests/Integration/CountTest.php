<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class CountTest extends TestCase
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
    public function count_returns_total_documents(): void
    {
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $book3 = Book::factory()->create();

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $count = Book::searchQuery(Query::matchAll())->count();

        $this->assertSame(3, $count);
    }

    #[Test]
    public function count_respects_query_filters(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $count = Book::searchQuery()
            ->filter(Query::term('author', 'John Doe'))
            ->count();

        $this->assertSame(2, $count);
    }

    #[Test]
    public function count_returns_zero_for_no_matches(): void
    {
        $book = Book::factory()->create(['author' => 'John Doe']);
        $book->searchable();

        $this->refreshIndex('books');

        $count = Book::searchQuery()
            ->filter(Query::term('author', 'NonExistent'))
            ->count();

        $this->assertSame(0, $count);
    }

    #[Test]
    public function count_works_with_bool_query(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe', 'price' => 10.0]);
        $book2 = Book::factory()->create(['author' => 'John Doe', 'price' => 50.0]);
        $book3 = Book::factory()->create(['author' => 'Jane Smith', 'price' => 20.0]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $count = Book::searchQuery()
            ->filter(Query::term('author', 'John Doe'))
            ->filter(Query::range('price')->gt(20.0))
            ->count();

        $this->assertSame(1, $count);
    }
}
