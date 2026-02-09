<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class BoolQueryTest extends TestCase
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
    public function test_bool_must_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'Great Adventures']);
        $book2 = Book::factory()->create(['title' => 'Great Expectations']);
        $book3 = Book::factory()->create(['title' => 'Something Else']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::bool()
            ->addMust(Query::match('title', 'Great')))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertNotContains($book3->id, $ids);
    }

    #[Test]
    public function test_bool_filter_query(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::bool()
            ->addFilter(Query::term('author', 'John Doe')))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book3->id, $ids);
        $this->assertNotContains($book2->id, $ids);
    }

    #[Test]
    public function test_bool_should_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'Great Book', 'author' => 'John Doe']);
        $book2 = Book::factory()->create(['title' => 'Good Book', 'author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['title' => 'Bad Book', 'author' => 'Bob Johnson']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::bool()
            ->addShould(Query::match('title', 'Great'))
            ->addShould(Query::match('title', 'Good'))
            ->minimumShouldMatch(1))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertNotContains($book3->id, $ids);
    }

    #[Test]
    public function test_bool_must_not_query(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'Bob Johnson']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::bool()
            ->addMust(Query::matchAll())
            ->addMustNot(Query::term('author', 'John Doe')))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertNotContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertContains($book3->id, $ids);
    }

    #[Test]
    public function test_bool_combined_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'Great Adventures', 'author' => 'John Doe']);
        $book2 = Book::factory()->create(['title' => 'Great Expectations', 'author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['title' => 'Great Stories', 'author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::bool()
            ->addMust(Query::match('title', 'Great'))
            ->addFilter(Query::term('author', 'John Doe')))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book3->id, $ids);
        $this->assertNotContains($book2->id, $ids);
    }

    #[Test]
    public function test_bool_query_shortcuts_on_search_builder(): void
    {
        $book1 = Book::factory()->create(['title' => 'Great Adventures', 'author' => 'John Doe']);
        $book2 = Book::factory()->create(['title' => 'Great Expectations', 'author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['title' => 'Great Stories', 'author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->must(Query::match('title', 'Great'))
            ->filter(Query::term('author', 'John Doe'))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book3->id, $ids);
        $this->assertNotContains($book2->id, $ids);
    }
}
