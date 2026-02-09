<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class CollapseTest extends TestCase
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
    public function test_collapse_by_field(): void
    {
        // Create multiple books by the same authors
        $book1 = Book::factory()->create([
            'title' => 'The Great Gatsby',
            'author' => 'F. Scott Fitzgerald',
            'price' => 10.99,
        ]);

        $book2 = Book::factory()->create([
            'title' => 'Tender Is the Night',
            'author' => 'F. Scott Fitzgerald',
            'price' => 12.99,
        ]);

        $book3 = Book::factory()->create([
            'title' => '1984',
            'author' => 'George Orwell',
            'price' => 14.99,
        ]);

        $book4 = Book::factory()->create([
            'title' => 'Animal Farm',
            'author' => 'George Orwell',
            'price' => 9.99,
        ]);

        $book5 = Book::factory()->create([
            'title' => 'To Kill a Mockingbird',
            'author' => 'Harper Lee',
            'price' => 11.99,
        ]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();
        $book4->searchable();
        $book5->searchable();

        $this->refreshIndex('books');

        // Search with collapse by author - should return only one book per author
        $result = Book::searchQuery(Query::matchAll())
            ->collapse('author')
            ->execute();

        // Should return 3 results (one per unique author)
        $this->assertSame(3, $result->hits()->count());

        $models = $result->models();
        $this->assertCount(3, $models);

        // Verify we have one result per author
        $authors = $models->pluck('author')->unique();
        $this->assertCount(3, $authors);
        $this->assertContains('F. Scott Fitzgerald', $authors);
        $this->assertContains('George Orwell', $authors);
        $this->assertContains('Harper Lee', $authors);
    }
}
