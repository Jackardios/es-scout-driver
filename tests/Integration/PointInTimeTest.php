<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class PointInTimeTest extends TestCase
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
    public function test_point_in_time_search(): void
    {
        $book1 = Book::factory()->create(['title' => 'The Great Gatsby']);
        $book2 = Book::factory()->create(['title' => 'Animal Farm']);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        // Open point in time
        $pitId = Book::openPointInTime('5m');
        $this->assertIsString($pitId);
        $this->assertNotEmpty($pitId);

        // Search using point in time
        $result = Book::searchQuery(Query::matchAll())
            ->pointInTime($pitId)
            ->execute();

        $this->assertSame(2, $result->total);
        $this->assertCount(2, $result->models());

        // Close point in time
        Book::closePointInTime($pitId);
    }

    #[Test]
    public function test_search_after_with_pit(): void
    {
        $book1 = Book::factory()->create([
            'title' => 'Book A',
            'price' => 10.99,
        ]);

        $book2 = Book::factory()->create([
            'title' => 'Book B',
            'price' => 15.99,
        ]);

        $book3 = Book::factory()->create([
            'title' => 'Book C',
            'price' => 20.99,
        ]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        // Open point in time
        $pitId = Book::openPointInTime('5m');

        // First search with size 1 and sort by price
        $firstResult = Book::searchQuery(Query::matchAll())
            ->pointInTime($pitId)
            ->sort('price', 'asc')
            ->size(1)
            ->execute();

        $this->assertSame(1, $firstResult->hits()->count());
        $this->assertSame($book1->id, $firstResult->models()->first()->id);

        // Get the sort values from the first result
        $rawFirstResult = Book::searchQuery(Query::matchAll())
            ->pointInTime($pitId)
            ->sort('price', 'asc')
            ->size(1)
            ->raw();

        $sortValues = $rawFirstResult['hits']['hits'][0]['sort'];
        $this->assertIsArray($sortValues);

        // Second search using search_after
        $secondResult = Book::searchQuery(Query::matchAll())
            ->pointInTime($pitId)
            ->sort('price', 'asc')
            ->size(1)
            ->searchAfter($sortValues)
            ->execute();

        $this->assertSame(1, $secondResult->hits()->count());
        $this->assertSame($book2->id, $secondResult->models()->first()->id);

        // Third search to get the last result
        $rawSecondResult = Book::searchQuery(Query::matchAll())
            ->pointInTime($pitId)
            ->sort('price', 'asc')
            ->size(1)
            ->searchAfter($sortValues)
            ->raw();

        $sortValues2 = $rawSecondResult['hits']['hits'][0]['sort'];

        $thirdResult = Book::searchQuery(Query::matchAll())
            ->pointInTime($pitId)
            ->sort('price', 'asc')
            ->size(1)
            ->searchAfter($sortValues2)
            ->execute();

        $this->assertSame(1, $thirdResult->hits()->count());
        $this->assertSame($book3->id, $thirdResult->models()->first()->id);

        // Close point in time
        Book::closePointInTime($pitId);
    }
}
