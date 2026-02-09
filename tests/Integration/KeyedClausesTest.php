<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class KeyedClausesTest extends TestCase
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
    public function test_keyed_clause_prevents_duplicate(): void
    {
        $book1 = Book::factory()->create(['tags' => ['active']]);
        $book2 = Book::factory()->create(['tags' => ['inactive']]);
        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $builder = Book::searchQuery(Query::matchAll());
        $builder->boolQuery()->addMust(Query::term('tags', 'active'), key: 'status_filter');
        $builder->boolQuery()->addMust(Query::term('tags', 'inactive'), key: 'status_filter');

        $boolQuery = $builder->boolQuery();
        $mustClauses = $boolQuery->getMustClauses();

        $this->assertCount(1, $mustClauses);
        $this->assertArrayHasKey('status_filter', $mustClauses);
    }

    #[Test]
    public function test_keyed_clause_lookup(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book1->searchable();
        $this->refreshIndex('books');

        $builder = Book::searchQuery(Query::matchAll());
        $builder->boolQuery()->addFilter(Query::term('author', 'John Doe'), key: 'author_filter');

        $boolQuery = $builder->boolQuery();

        $this->assertTrue($boolQuery->hasClause('filter', 'author_filter'));
        $this->assertNotNull($boolQuery->getClause('filter', 'author_filter'));
        $this->assertFalse($boolQuery->hasClause('filter', 'non_existent_key'));
        $this->assertNull($boolQuery->getClause('filter', 'non_existent_key'));
    }

    #[Test]
    public function test_keyed_clauses_execute_correctly(): void
    {
        $book1 = Book::factory()->create(['title' => 'Great Book', 'author' => 'John Doe', 'tags' => ['fiction']]);
        $book2 = Book::factory()->create(['title' => 'Good Book', 'author' => 'Jane Smith', 'tags' => ['fiction']]);
        $book3 = Book::factory()->create(['title' => 'Bad Book', 'author' => 'John Doe', 'tags' => ['non-fiction']]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $builder = Book::searchQuery(Query::matchAll());
        $builder->boolQuery()->addMust(Query::match('title', 'book'), key: 'title_match');
        $builder->boolQuery()->addFilter(Query::term('author', 'John Doe'), key: 'author_filter');
        $builder->boolQuery()->addFilter(Query::term('tags', 'fiction'));

        $boolQuery = $builder->boolQuery();
        $this->assertTrue($boolQuery->hasClause('must', 'title_match'));
        $this->assertTrue($boolQuery->hasClause('filter', 'author_filter'));

        $filterClauses = $boolQuery->getFilterClauses();
        $this->assertCount(2, $filterClauses);

        $result = $builder->execute();

        $this->assertSame(1, $result->total);
        $this->assertSame($book1->id, $result->models()->first()->id);
    }
}
