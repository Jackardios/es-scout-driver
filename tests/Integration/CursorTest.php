<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Search\Hit;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class CursorTest extends TestCase
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
    public function cursor_iterates_all_documents(): void
    {
        $books = Book::factory()->count(5)->create();
        foreach ($books as $book) {
            $book->searchable();
        }

        $this->refreshIndex('books');

        $cursor = Book::searchQuery(Query::matchAll())->cursor(2);

        $hits = [];
        foreach ($cursor as $hit) {
            $hits[] = $hit;
        }

        $this->assertCount(5, $hits);
        $this->assertContainsOnlyInstancesOf(Hit::class, $hits);
    }

    #[Test]
    public function cursor_respects_chunk_size(): void
    {
        $books = Book::factory()->count(10)->create();
        foreach ($books as $book) {
            $book->searchable();
        }

        $this->refreshIndex('books');

        $cursor = Book::searchQuery(Query::matchAll())->cursor(3);

        $count = 0;
        foreach ($cursor as $hit) {
            $count++;
        }

        $this->assertSame(10, $count);
    }

    #[Test]
    public function cursor_respects_query_filters(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'John Doe']);
        $book4 = Book::factory()->create(['author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();
        $book4->searchable();

        $this->refreshIndex('books');

        $cursor = Book::searchQuery()
            ->filter(Query::term('author', 'John Doe'))
            ->cursor(2);

        $count = 0;
        foreach ($cursor as $hit) {
            $count++;
        }

        $this->assertSame(3, $count);
    }

    #[Test]
    public function chunk_calls_callback_with_chunks(): void
    {
        $books = Book::factory()->count(5)->create();
        foreach ($books as $book) {
            $book->searchable();
        }

        $this->refreshIndex('books');

        $chunks = [];
        Book::searchQuery(Query::matchAll())->chunk(2, function ($hits) use (&$chunks) {
            $chunks[] = $hits;
        });

        $this->assertCount(3, $chunks);
        $this->assertCount(2, $chunks[0]);
        $this->assertCount(2, $chunks[1]);
        $this->assertCount(1, $chunks[2]);
    }

    #[Test]
    public function chunk_stops_when_callback_returns_false(): void
    {
        $books = Book::factory()->count(10)->create();
        foreach ($books as $book) {
            $book->searchable();
        }

        $this->refreshIndex('books');

        $processedChunks = 0;
        Book::searchQuery(Query::matchAll())->chunk(2, function ($hits) use (&$processedChunks) {
            $processedChunks++;
            return $processedChunks < 2;
        });

        $this->assertSame(2, $processedChunks);
    }

    #[Test]
    public function cursor_with_from_offset_handles_search_after_pagination(): void
    {
        $books = Book::factory()->count(5)->create();

        $price = 1.0;
        foreach ($books as $book) {
            $book->price = $price;
            $book->save();
            $book->searchable();
            $price += 1.0;
        }

        $this->refreshIndex('books');

        $cursor = Book::searchQuery(Query::matchAll())
            ->sort('price', 'asc')
            ->from(1)
            ->cursor(2);

        $prices = [];
        foreach ($cursor as $hit) {
            $prices[] = (float) $hit->source['price'];
        }

        $this->assertSame([2.0, 3.0, 4.0, 5.0], $prices);
    }

    #[Test]
    public function cursor_returns_empty_for_no_matches(): void
    {
        $book = Book::factory()->create(['author' => 'John Doe']);
        $book->searchable();

        $this->refreshIndex('books');

        $cursor = Book::searchQuery()
            ->filter(Query::term('author', 'NonExistent'))
            ->cursor();

        $count = 0;
        foreach ($cursor as $hit) {
            $count++;
        }

        $this->assertSame(0, $count);
    }
}
