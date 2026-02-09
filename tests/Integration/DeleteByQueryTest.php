<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class DeleteByQueryTest extends TestCase
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
    public function delete_by_query_removes_matching_documents(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery()
            ->filter(Query::term('author', 'John Doe'))
            ->deleteByQuery();

        $this->refreshIndex('books');

        $this->assertSame(2, $result['deleted']);

        $count = Book::searchQuery(Query::matchAll())->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function delete_by_query_returns_result_structure(): void
    {
        $book = Book::factory()->create(['author' => 'John Doe']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery()
            ->filter(Query::term('author', 'John Doe'))
            ->deleteByQuery();

        $this->assertArrayHasKey('deleted', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('failures', $result);
    }

    #[Test]
    public function delete_by_query_with_no_matches(): void
    {
        $book = Book::factory()->create(['author' => 'John Doe']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery()
            ->filter(Query::term('author', 'NonExistent'))
            ->deleteByQuery();

        $this->assertSame(0, $result['deleted']);

        $count = Book::searchQuery(Query::matchAll())->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function update_by_query_updates_matching_documents(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe', 'price' => 10.0]);
        $book2 = Book::factory()->create(['author' => 'Jane Smith', 'price' => 20.0]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery()
            ->filter(Query::term('author', 'John Doe'))
            ->updateByQuery([
                'source' => 'ctx._source.price = ctx._source.price * 2',
                'lang' => 'painless',
            ]);

        $this->refreshIndex('books');

        $this->assertSame(1, $result['updated']);

        $searchResult = Book::searchQuery()
            ->filter(Query::term('author', 'John Doe'))
            ->execute();

        $this->assertSame(20.0, $searchResult->hits()->first()->source['price']);
    }

    #[Test]
    public function update_by_query_returns_result_structure(): void
    {
        $book = Book::factory()->create(['author' => 'John Doe']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery()
            ->filter(Query::term('author', 'John Doe'))
            ->updateByQuery([
                'source' => 'ctx._source.author = "Updated"',
                'lang' => 'painless',
            ]);

        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('failures', $result);
    }
}
