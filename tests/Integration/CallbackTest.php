<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class CallbackTest extends TestCase
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
    public function test_modify_query_modifies_model_loading(): void
    {
        $book1 = Book::factory()->create(['title' => 'Book One', 'price' => 10.00]);
        $book2 = Book::factory()->create(['title' => 'Book Two', 'price' => 50.00]);
        $book3 = Book::factory()->create(['title' => 'Book Three', 'price' => 30.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();
        $this->refreshIndex('books');

        // Use modifyQuery that adds a WHERE clause, excluding cheap books
        $builder = Book::searchQuery(Query::matchAll());
        $builder->modifyQuery(function ($query) {
            $query->where('price', '>=', 25.0);
        });

        $result = $builder->execute();

        // ES finds 3 hits, but model hydration filters by the query callback
        $this->assertSame(3, $result->total);
        $models = $result->models();
        $this->assertCount(2, $models);

        $ids = $models->pluck('id')->toArray();
        $this->assertContains($book2->id, $ids);
        $this->assertContains($book3->id, $ids);
        $this->assertNotContains($book1->id, $ids);
    }

    #[Test]
    public function test_modify_models_transforms_results(): void
    {
        $book1 = Book::factory()->create(['title' => 'Expensive Book', 'price' => 50.00]);
        $book2 = Book::factory()->create(['title' => 'Cheap Book', 'price' => 10.00]);
        $book3 = Book::factory()->create(['title' => 'Medium Book', 'price' => 25.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $builder = Book::searchQuery(Query::matchAll());
        $builder->modifyModels(function ($collection) {
            return $collection->filter(function ($book) {
                return $book->price >= 25.00;
            })->values();
        });

        $result = $builder->execute();

        $this->assertSame(3, $result->total);

        $models = $result->models();
        $this->assertCount(2, $models);

        $ids = $models->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book3->id, $ids);
        $this->assertNotContains($book2->id, $ids);
    }
}
