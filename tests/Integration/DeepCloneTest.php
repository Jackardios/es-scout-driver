<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class DeepCloneTest extends TestCase
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
    public function test_clone_isolates_bool_query(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe', 'tags' => ['fiction']]);
        $book2 = Book::factory()->create(['author' => 'Jane Smith', 'tags' => ['fiction']]);
        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $builder1 = Book::searchQuery(Query::matchAll());
        $builder1->filter(Query::term('author', 'John Doe'));

        $builder2 = clone $builder1;
        $builder2->filter(Query::term('tags', 'fiction'));

        $boolQuery1 = $builder1->boolQuery();
        $filterClauses1 = $boolQuery1->getFilterClauses();
        $this->assertCount(1, $filterClauses1);

        $boolQuery2 = $builder2->boolQuery();
        $filterClauses2 = $boolQuery2->getFilterClauses();
        $this->assertCount(2, $filterClauses2);
    }

    #[Test]
    public function test_clone_isolates_sort(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book A', 'price' => 10.00]);
        $book2 = Book::factory()->create(['title' => 'Book B', 'price' => 20.00]);
        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $builder1 = Book::searchQuery(Query::matchAll());
        $builder1->sort('price', 'asc');

        $builder2 = clone $builder1;
        $builder2->sort('title', 'desc');

        $sort1 = $builder1->getSort();
        $this->assertSame([['price' => 'asc']], $sort1);

        $sort2 = $builder2->getSort();
        $this->assertSame([['price' => 'asc'], ['title' => 'desc']], $sort2);
    }

    #[Test]
    public function test_cloned_builder_executes_independently(): void
    {
        $book1 = Book::factory()->create(['title' => 'Great Book', 'author' => 'John Doe', 'tags' => ['fiction']]);
        $book2 = Book::factory()->create(['title' => 'Good Book', 'author' => 'Jane Smith', 'tags' => ['non-fiction']]);
        $book3 = Book::factory()->create(['title' => 'Amazing Book', 'author' => 'John Doe', 'tags' => ['non-fiction']]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $builder1 = Book::searchQuery(Query::matchAll());
        $builder1->filter(Query::term('author', 'John Doe'));

        $builder2 = clone $builder1;
        $builder2->filter(Query::term('tags', 'fiction'));

        $result1 = $builder1->execute();
        $this->assertSame(2, $result1->total);
        $ids1 = $result1->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids1);
        $this->assertContains($book3->id, $ids1);

        $result2 = $builder2->execute();
        $this->assertSame(1, $result2->total);
        $ids2 = $result2->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids2);
        $this->assertNotContains($book3->id, $ids2);
    }
}
