<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class RescoreTest extends TestCase
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
    public function test_rescore_boosts_matching_documents(): void
    {
        $book1 = Book::factory()->create([
            'title' => 'Elasticsearch Basics',
            'description' => 'Introduction to search engines',
        ]);
        $book2 = Book::factory()->create([
            'title' => 'Advanced Search',
            'description' => 'Deep dive into Elasticsearch features',
        ]);
        $book3 = Book::factory()->create([
            'title' => 'Database Systems',
            'description' => 'SQL and NoSQL databases',
        ]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('title', 'search'))
            ->rescore(
                Query::matchPhrase('description', 'Elasticsearch'),
                windowSize: 10,
                queryWeight: 1.0,
                rescoreQueryWeight: 2.0
            )
            ->execute();

        $this->assertSame(2, $result->total);

        $models = $result->models();
        $this->assertSame($book2->id, $models->first()->id);
    }

    #[Test]
    public function test_rescore_raw(): void
    {
        $book1 = Book::factory()->create([
            'title' => 'Programming Guide',
            'description' => 'Learn programming with examples',
        ]);
        $book2 = Book::factory()->create([
            'title' => 'Programming Tutorial',
            'description' => 'Step by step programming tutorial',
        ]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('title', 'programming'))
            ->rescoreRaw([
                'window_size' => 100,
                'query' => [
                    'rescore_query' => [
                        'match_phrase' => [
                            'description' => 'step by step',
                        ],
                    ],
                    'query_weight' => 0.7,
                    'rescore_query_weight' => 1.2,
                ],
            ])
            ->execute();

        $this->assertSame(2, $result->total);

        $models = $result->models();
        $this->assertSame($book2->id, $models->first()->id);
    }

    #[Test]
    public function test_rescore_with_closure(): void
    {
        $book1 = Book::factory()->create([
            'title' => 'Laravel Guide',
            'description' => 'Web development with Laravel',
        ]);
        $book2 = Book::factory()->create([
            'title' => 'Laravel Cookbook',
            'description' => 'Advanced Laravel techniques and patterns',
        ]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('title', 'laravel'))
            ->rescore(fn() => Query::match('description', 'advanced'))
            ->execute();

        $this->assertSame(2, $result->total);

        $models = $result->models();
        $this->assertSame($book2->id, $models->first()->id);
    }
}
