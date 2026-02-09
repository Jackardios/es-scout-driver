<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class SortTest extends TestCase
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
    public function test_sort_by_field_ascending(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book A', 'price' => 29.99]);
        $book2 = Book::factory()->create(['title' => 'Book B', 'price' => 19.99]);
        $book3 = Book::factory()->create(['title' => 'Book C', 'price' => 39.99]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->sort('price', 'asc')
            ->execute();

        $this->assertSame(3, $result->total);

        $models = $result->models();
        $this->assertCount(3, $models);

        $prices = $models->pluck('price')->toArray();
        $this->assertSame([19.99, 29.99, 39.99], $prices);

        $this->assertSame($book2->id, $models[0]->id);
        $this->assertSame($book1->id, $models[1]->id);
        $this->assertSame($book3->id, $models[2]->id);
    }

    #[Test]
    public function test_sort_by_field_descending(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book A', 'price' => 29.99]);
        $book2 = Book::factory()->create(['title' => 'Book B', 'price' => 19.99]);
        $book3 = Book::factory()->create(['title' => 'Book C', 'price' => 39.99]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->sort('price', 'desc')
            ->execute();

        $this->assertSame(3, $result->total);

        $models = $result->models();
        $this->assertCount(3, $models);

        $prices = $models->pluck('price')->toArray();
        $this->assertSame([39.99, 29.99, 19.99], $prices);

        $this->assertSame($book3->id, $models[0]->id);
        $this->assertSame($book1->id, $models[1]->id);
        $this->assertSame($book2->id, $models[2]->id);
    }

    #[Test]
    public function test_sort_raw(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book A', 'price' => 29.99, 'author' => 'Zoe Author']);
        $book2 = Book::factory()->create(['title' => 'Book B', 'price' => 29.99, 'author' => 'Alice Author']);
        $book3 = Book::factory()->create(['title' => 'Book C', 'price' => 39.99, 'author' => 'Bob Author']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->sortRaw([
                ['price' => 'desc'],
                ['author' => 'asc'],
            ])
            ->execute();

        $this->assertSame(3, $result->total);

        $models = $result->models();
        $this->assertCount(3, $models);

        // Book C has highest price (39.99)
        $this->assertSame($book3->id, $models[0]->id);

        // Books A and B both have price 29.99, so sorted by author (Alice < Zoe)
        $this->assertSame($book2->id, $models[1]->id);
        $this->assertSame($book1->id, $models[2]->id);
    }

    #[Test]
    public function test_multiple_sort_criteria(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book A', 'price' => 25.00, 'author' => 'John Doe']);
        $book2 = Book::factory()->create(['title' => 'Book B', 'price' => 25.00, 'author' => 'Alice Smith']);
        $book3 = Book::factory()->create(['title' => 'Book C', 'price' => 30.00, 'author' => 'Bob Jones']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->sort('price', 'asc')
            ->sort('author', 'asc')
            ->execute();

        $this->assertSame(3, $result->total);

        $models = $result->models();

        // First by price (25.00 < 30.00), then by author within same price
        $this->assertSame($book2->id, $models[0]->id); // 25.00, Alice
        $this->assertSame($book1->id, $models[1]->id); // 25.00, John
        $this->assertSame($book3->id, $models[2]->id); // 30.00, Bob
    }

    // ---- Tests for Sort factory ----

    #[Test]
    public function test_sort_with_sort_interface(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book A', 'price' => 29.99]);
        $book2 = Book::factory()->create(['title' => 'Book B', 'price' => 19.99]);
        $book3 = Book::factory()->create(['title' => 'Book C', 'price' => 39.99]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->sort(Sort::field('price')->desc())
            ->execute();

        $this->assertSame(3, $result->total);

        $models = $result->models();
        $prices = $models->pluck('price')->toArray();
        $this->assertSame([39.99, 29.99, 19.99], $prices);
    }

    #[Test]
    public function test_sort_by_score(): void
    {
        $book1 = Book::factory()->create(['title' => 'Elasticsearch Guide']);
        $book2 = Book::factory()->create(['title' => 'Laravel Guide']);
        $book3 = Book::factory()->create(['title' => 'Elasticsearch and Laravel']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('title', 'elasticsearch'))
            ->sort(Sort::score()->desc())
            ->execute();

        $this->assertSame(2, $result->total);
    }

    #[Test]
    public function test_sort_with_missing(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book A', 'price' => 29.99]);
        $book2 = Book::factory()->create(['title' => 'Book B', 'price' => 19.99]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->sort(Sort::field('price')->desc()->missingLast())
            ->execute();

        $this->assertSame(2, $result->total);
        $models = $result->models();
        $this->assertSame($book1->id, $models[0]->id);
        $this->assertSame($book2->id, $models[1]->id);
    }

    #[Test]
    public function test_multiple_sort_with_sort_interface(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book A', 'price' => 25.00, 'author' => 'John Doe']);
        $book2 = Book::factory()->create(['title' => 'Book B', 'price' => 25.00, 'author' => 'Alice Smith']);
        $book3 = Book::factory()->create(['title' => 'Book C', 'price' => 30.00, 'author' => 'Bob Jones']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->sort(Sort::field('price')->asc())
            ->sort(Sort::field('author')->asc())
            ->execute();

        $models = $result->models();

        $this->assertSame($book2->id, $models[0]->id); // 25.00, Alice
        $this->assertSame($book1->id, $models[1]->id); // 25.00, John
        $this->assertSame($book3->id, $models[2]->id); // 30.00, Bob
    }
}
