<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class ExplainTest extends TestCase
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
    public function test_explain_returns_explanation(): void
    {
        $book1 = Book::factory()->create([
            'title' => 'The Great Gatsby',
            'author' => 'F. Scott Fitzgerald',
            'description' => 'A novel about the American Dream',
        ]);

        $book2 = Book::factory()->create([
            'title' => 'Animal Farm',
            'author' => 'George Orwell',
            'description' => 'A satirical allegorical novella',
        ]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        // Search with explain enabled
        $result = Book::searchQuery(Query::match('description', 'novel'))
            ->explain(true)
            ->execute();

        $this->assertSame(1, $result->total);

        $hits = $result->hits();
        $this->assertCount(1, $hits);

        // Get the raw hit data
        $rawResult = Book::searchQuery(Query::match('description', 'novel'))
            ->explain(true)
            ->raw();

        // Verify explanation exists in raw response
        $this->assertArrayHasKey('hits', $rawResult);
        $this->assertArrayHasKey('hits', $rawResult['hits']);
        $this->assertNotEmpty($rawResult['hits']['hits']);

        $firstHit = $rawResult['hits']['hits'][0];
        $this->assertArrayHasKey('_explanation', $firstHit);
        $this->assertIsArray($firstHit['_explanation']);
        $this->assertNotEmpty($firstHit['_explanation']);

        // Verify explanation has expected structure
        $this->assertArrayHasKey('value', $firstHit['_explanation']);
        $this->assertArrayHasKey('description', $firstHit['_explanation']);
    }
}
