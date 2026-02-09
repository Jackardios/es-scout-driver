<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class PaginationTest extends TestCase
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
    public function test_paginate_returns_correct_page(): void
    {
        $books = [];
        for ($i = 1; $i <= 5; $i++) {
            $books[$i] = Book::factory()->create([
                'title' => "Book {$i}",
                'price' => $i * 10.0,
            ]);
            $books[$i]->searchable();
        }

        $this->refreshIndex('books');

        // First page
        $firstPage = Book::searchQuery(Query::matchAll())
            ->sort('price', 'asc')
            ->paginate(2, 'page', 1)
            ->withModels();

        $this->assertSame(5, $firstPage->total());
        $this->assertCount(2, $firstPage->items());
        $this->assertSame(1, $firstPage->currentPage());
        $this->assertSame(3, $firstPage->lastPage());

        $firstPageItems = $firstPage->items();
        $this->assertSame($books[1]->id, $firstPageItems[0]->id);
        $this->assertSame($books[2]->id, $firstPageItems[1]->id);

        // Second page
        $secondPage = Book::searchQuery(Query::matchAll())
            ->sort('price', 'asc')
            ->paginate(2, 'page', 2)
            ->withModels();

        $this->assertSame(5, $secondPage->total());
        $this->assertCount(2, $secondPage->items());
        $this->assertSame(2, $secondPage->currentPage());

        $secondPageItems = $secondPage->items();
        $this->assertSame($books[3]->id, $secondPageItems[0]->id);
        $this->assertSame($books[4]->id, $secondPageItems[1]->id);

        // Third page
        $thirdPage = Book::searchQuery(Query::matchAll())
            ->sort('price', 'asc')
            ->paginate(2, 'page', 3)
            ->withModels();

        $this->assertSame(5, $thirdPage->total());
        $this->assertCount(1, $thirdPage->items());
        $this->assertSame(3, $thirdPage->currentPage());

        $thirdPageItems = $thirdPage->items();
        $this->assertSame($books[5]->id, $thirdPageItems[0]->id);
    }

    #[Test]
    public function test_from_and_size(): void
    {
        $books = [];
        for ($i = 1; $i <= 10; $i++) {
            $books[$i] = Book::factory()->create([
                'title' => "Book {$i}",
                'price' => $i * 10.0,
            ]);
            $books[$i]->searchable();
        }

        $this->refreshIndex('books');

        // Skip first 3, take 4
        $result = Book::searchQuery(Query::matchAll())
            ->sort('price', 'asc')
            ->from(3)
            ->size(4)
            ->execute();

        $this->assertSame(10, $result->total);

        $models = $result->models();
        $this->assertCount(4, $models);

        // Should get books 4, 5, 6, 7 (0-indexed, so from position 3)
        $this->assertSame($books[4]->id, $models[0]->id);
        $this->assertSame($books[5]->id, $models[1]->id);
        $this->assertSame($books[6]->id, $models[2]->id);
        $this->assertSame($books[7]->id, $models[3]->id);
    }

    #[Test]
    public function test_paginator_total(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $book = Book::factory()->create(['title' => "Book {$i}"]);
            $book->searchable();
        }

        $this->refreshIndex('books');

        $paginator = Book::searchQuery(Query::matchAll())
            ->paginate(5);

        $this->assertSame(15, $paginator->total());
        $this->assertSame(3, $paginator->lastPage());
        $this->assertCount(5, $paginator->items());
    }

    #[Test]
    public function test_size_limits_results(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $book = Book::factory()->create(['title' => "Book {$i}"]);
            $book->searchable();
        }

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->size(3)
            ->execute();

        $this->assertSame(10, $result->total);
        $this->assertCount(3, $result->models());
    }

    #[Test]
    public function test_pagination_with_query(): void
    {
        $books = [];
        for ($i = 1; $i <= 8; $i++) {
            $books[$i] = Book::factory()->create([
                'title' => "Elasticsearch Book {$i}",
                'price' => $i * 10.0,
            ]);
            $books[$i]->searchable();
        }

        // Add some non-matching books
        Book::factory()->create(['title' => 'Different Book'])->searchable();
        Book::factory()->create(['title' => 'Another Book'])->searchable();

        $this->refreshIndex('books');

        $paginator = Book::searchQuery(Query::match('title', 'Elasticsearch'))
            ->sort('price', 'asc')
            ->paginate(3)
            ->withModels();

        $this->assertSame(8, $paginator->total());
        $this->assertSame(3, $paginator->lastPage());
        $this->assertCount(3, $paginator->items());

        $items = $paginator->items();
        $this->assertSame($books[1]->id, $items[0]->id);
        $this->assertSame($books[2]->id, $items[1]->id);
        $this->assertSame($books[3]->id, $items[2]->id);
    }
}
