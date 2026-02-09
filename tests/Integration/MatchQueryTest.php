<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class MatchQueryTest extends TestCase
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
    public function test_match_query_returns_matching_results(): void
    {
        $book1 = Book::factory()->create(['title' => 'The Great Gatsby']);
        $book2 = Book::factory()->create(['title' => 'The Great Adventure']);
        $book3 = Book::factory()->create(['title' => 'Something Else']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('title', 'Great'))
            ->execute();

        $this->assertSame(2, $result->total);

        $models = $result->models();
        $this->assertCount(2, $models);

        $ids = $models->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertNotContains($book3->id, $ids);
    }

    #[Test]
    public function test_match_query_with_fuzziness(): void
    {
        $book = Book::factory()->create(['title' => 'Elasticsearch Guide']);
        $book->searchable();

        $this->refreshIndex('books');

        // Search with a typo - "Elastcsearch" instead of "Elasticsearch"
        $result = Book::searchQuery((new MatchQuery('title', 'Elastcsearch'))->fuzziness('AUTO'))
            ->execute();

        $this->assertSame(1, $result->total);
        $this->assertSame($book->id, $result->models()->first()->id);
    }

    #[Test]
    public function test_match_query_returns_empty_for_no_match(): void
    {
        $book = Book::factory()->create(['title' => 'The Great Gatsby']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('title', 'nonexistent'))
            ->execute();

        $this->assertSame(0, $result->total);
        $this->assertCount(0, $result->models());
    }
}
