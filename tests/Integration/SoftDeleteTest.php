<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class SoftDeleteTest extends TestCase
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
                    '__soft_deleted' => ['type' => 'integer'],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteIndex('books');
        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('scout.soft_delete', true);
    }

    #[Test]
    public function test_soft_deleted_models_are_indexed_with_flag(): void
    {
        $book = Book::factory()->create(['title' => 'Test Book']);
        $book->searchable();
        $this->refreshIndex('books');

        // Soft delete and re-index
        $book->delete();
        $book->searchable();
        $this->refreshIndex('books');

        // Search raw for the document
        $response = $this->client->search([
            'index' => 'books',
            'body' => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ]);

        $hits = $response['hits']['hits'];
        $this->assertCount(1, $hits);
        $this->assertSame(1, $hits[0]['_source']['__soft_deleted']);
    }

    #[Test]
    public function test_with_trashed_loads_soft_deleted_models(): void
    {
        $book1 = Book::factory()->create(['title' => 'Active Book']);
        $book2 = Book::factory()->create(['title' => 'Deleted Book']);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        // Soft delete book2 and re-index
        $book2->delete();
        $book2->searchable();
        $this->refreshIndex('books');

        // Search with withTrashed
        $builder = Book::searchQuery(Query::matchAll());
        $builder->boolQuery()->withTrashed();
        $result = $builder->execute();

        $this->assertSame(2, $result->total);
        $models = $result->models();
        $this->assertCount(2, $models);

        $ids = $models->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
    }

    #[Test]
    public function test_only_trashed_loads_only_soft_deleted_models(): void
    {
        $book1 = Book::factory()->create(['title' => 'Active Book']);
        $book2 = Book::factory()->create(['title' => 'Deleted Book']);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        // Soft delete book2 and re-index
        $book2->delete();
        $book2->searchable();
        $this->refreshIndex('books');

        // Search with onlyTrashed
        $builder = Book::searchQuery(Query::matchAll());
        $builder->boolQuery()->onlyTrashed();
        $result = $builder->execute();

        // ES should return only soft-deleted documents
        $this->assertSame(1, $result->total);
        $models = $result->models();
        $this->assertCount(1, $models);
        $this->assertSame($book2->id, $models->first()->id);
    }

    #[Test]
    public function test_default_search_excludes_soft_deleted_at_es_level(): void
    {
        $book1 = Book::factory()->create(['title' => 'Active Book']);
        $book2 = Book::factory()->create(['title' => 'Deleted Book']);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        // Soft delete book2 and re-index
        $book2->delete();
        $book2->searchable();
        $this->refreshIndex('books');

        // Search without withTrashed - ES-level filter should exclude soft-deleted
        $result = Book::searchQuery(Query::matchAll())->execute();

        // ES now returns only 1 document (soft delete filter applied at ES level)
        $this->assertSame(1, $result->total);

        // models() also returns 1 since ES already filtered
        $models = $result->models();
        $this->assertCount(1, $models);
        $this->assertSame($book1->id, $models->first()->id);
    }
}
