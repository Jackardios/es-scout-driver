<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Query\Term\RangeQuery;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class RangeQueryTest extends TestCase
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
    public function test_range_query_greater_than(): void
    {
        $book1 = Book::factory()->create(['title' => 'Cheap Book', 'price' => 10.00]);
        $book2 = Book::factory()->create(['title' => 'Medium Book', 'price' => 25.00]);
        $book3 = Book::factory()->create(['title' => 'Expensive Book', 'price' => 50.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery((new RangeQuery('price'))->gt(20.00))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertNotContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertContains($book3->id, $ids);
    }

    #[Test]
    public function test_range_query_between(): void
    {
        $book1 = Book::factory()->create(['title' => 'Cheap Book', 'price' => 10.00]);
        $book2 = Book::factory()->create(['title' => 'Medium Book', 'price' => 25.00]);
        $book3 = Book::factory()->create(['title' => 'Expensive Book', 'price' => 50.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery((new RangeQuery('price'))->gte(20.00)->lte(30.00))
            ->execute();

        $this->assertSame(1, $result->total);

        $models = $result->models();
        $this->assertCount(1, $models);
        $this->assertSame($book2->id, $models->first()->id);
    }
}
