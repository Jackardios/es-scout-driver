<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class SearchOptionsTest extends TestCase
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
    public function test_min_score_filters_low_score_documents(): void
    {
        $book1 = Book::factory()->create([
            'title' => 'Elasticsearch Elasticsearch Elasticsearch Guide',
            'description' => 'Deep dive into Elasticsearch',
        ]);
        $book2 = Book::factory()->create([
            'title' => 'Database Guide',
            'description' => 'Learn about Elasticsearch basics',
        ]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $resultWithoutMinScore = Book::searchQuery(Query::match('title', 'elasticsearch'))
            ->execute();

        $this->assertSame(1, $resultWithoutMinScore->total);

        $hit = $resultWithoutMinScore->hits()->first();
        $highScore = $hit->score;

        $resultWithHighMinScore = Book::searchQuery(Query::match('title', 'elasticsearch'))
            ->minScore($highScore + 1.0)
            ->execute();

        $this->assertSame(0, $resultWithHighMinScore->total);
    }

    #[Test]
    public function test_track_total_hits_with_int_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $book = Book::factory()->create(['title' => "Book {$i}"]);
            $book->searchable();
        }

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->trackTotalHits(3)
            ->execute();

        $this->assertGreaterThanOrEqual(3, $result->total);
    }

    #[Test]
    public function test_track_total_hits_with_bool(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $book = Book::factory()->create(['title' => "Book {$i}"]);
            $book->searchable();
        }

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->trackTotalHits(true)
            ->execute();

        $this->assertSame(5, $result->total);
    }

    #[Test]
    public function test_track_scores_returns_scores_with_sort(): void
    {
        $book1 = Book::factory()->create(['title' => 'Elasticsearch Guide', 'price' => 29.99]);
        $book2 = Book::factory()->create(['title' => 'Elasticsearch Basics', 'price' => 19.99]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $resultWithoutTrackScores = Book::searchQuery(Query::match('title', 'elasticsearch'))
            ->sort('price', 'asc')
            ->execute();

        $resultWithTrackScores = Book::searchQuery(Query::match('title', 'elasticsearch'))
            ->sort('price', 'asc')
            ->trackScores(true)
            ->execute();

        $this->assertSame(2, $resultWithTrackScores->total);

        foreach ($resultWithTrackScores->hits() as $hit) {
            $this->assertNotNull($hit->score);
            $this->assertGreaterThan(0, $hit->score);
        }
    }

    #[Test]
    public function test_timeout_parameter_is_sent(): void
    {
        $book = Book::factory()->create(['title' => 'Test Book']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->timeout('10s')
            ->execute();

        $this->assertSame(1, $result->total);
    }

    #[Test]
    public function test_preference_parameter_is_sent(): void
    {
        $book = Book::factory()->create(['title' => 'Test Book']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->preference('_local')
            ->execute();

        $this->assertSame(1, $result->total);
    }

    #[Test]
    public function test_search_type_parameter_is_sent(): void
    {
        $book = Book::factory()->create(['title' => 'Test Book']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->searchType('dfs_query_then_fetch')
            ->execute();

        $this->assertSame(1, $result->total);
    }

    #[Test]
    public function test_version_returns_document_versions(): void
    {
        $book = Book::factory()->create(['title' => 'Test Book']);
        $book->searchable();

        $this->refreshIndex('books');

        $rawResult = Book::searchQuery(Query::matchAll())
            ->version()
            ->raw();

        $this->assertArrayHasKey('hits', $rawResult);
        $this->assertNotEmpty($rawResult['hits']['hits']);

        $firstHit = $rawResult['hits']['hits'][0];
        $this->assertArrayHasKey('_version', $firstHit);
        $this->assertGreaterThan(0, $firstHit['_version']);
    }

    #[Test]
    public function test_terminate_after_limits_documents_scanned(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $book = Book::factory()->create(['title' => "Book {$i}"]);
            $book->searchable();
        }

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->terminateAfter(3)
            ->execute();

        $this->assertLessThanOrEqual(3, $result->hits()->count());
    }

    #[Test]
    public function test_runtime_mappings(): void
    {
        $book = Book::factory()->create(['title' => 'Test Book', 'price' => 29.99]);
        $book->searchable();

        $this->refreshIndex('books');

        $rawResult = Book::searchQuery(Query::matchAll())
            ->runtimeMappings([
                'price_with_tax' => [
                    'type' => 'double',
                    'script' => [
                        'source' => "emit(doc['price'].value * 1.2)",
                    ],
                ],
            ])
            ->scriptFields([
                'calculated_price' => [
                    'script' => [
                        'source' => "doc['price_with_tax'].value",
                    ],
                ],
            ])
            ->raw();

        $this->assertArrayHasKey('hits', $rawResult);
        $this->assertNotEmpty($rawResult['hits']['hits']);

        $firstHit = $rawResult['hits']['hits'][0];
        $this->assertArrayHasKey('fields', $firstHit);
        $this->assertArrayHasKey('calculated_price', $firstHit['fields']);

        $calculatedPrice = $firstHit['fields']['calculated_price'][0];
        $this->assertEqualsWithDelta(35.988, $calculatedPrice, 0.01);
    }

    #[Test]
    public function test_script_fields(): void
    {
        $book = Book::factory()->create(['title' => 'Test Book', 'price' => 100.00]);
        $book->searchable();

        $this->refreshIndex('books');

        $rawResult = Book::searchQuery(Query::matchAll())
            ->scriptFields([
                'doubled_price' => [
                    'script' => [
                        'source' => "doc['price'].value * 2",
                    ],
                ],
            ])
            ->raw();

        $this->assertArrayHasKey('hits', $rawResult);
        $this->assertNotEmpty($rawResult['hits']['hits']);

        $firstHit = $rawResult['hits']['hits'][0];
        $this->assertArrayHasKey('fields', $firstHit);
        $this->assertArrayHasKey('doubled_price', $firstHit['fields']);
        $this->assertSame(200.0, $firstHit['fields']['doubled_price'][0]);
    }
}
