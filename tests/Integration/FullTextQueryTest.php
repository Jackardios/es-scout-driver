<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class FullTextQueryTest extends TestCase
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
    public function test_match_phrase_query(): void
    {
        $book = Book::factory()->create(['title' => 'the quick brown fox jumps over']);
        $book->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchPhrase('title', 'quick brown fox'))
            ->execute();

        $this->assertSame(1, $result->total);
        $this->assertSame($book->id, $result->models()->first()->id);
    }

    #[Test]
    public function test_match_phrase_prefix_query(): void
    {
        $book = Book::factory()->create(['title' => 'elasticsearch tutorial guide']);
        $book->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchPhrasePrefix('title', 'elasticsearch tuto'))
            ->execute();

        $this->assertSame(1, $result->total);
        $this->assertSame($book->id, $result->models()->first()->id);
    }

    #[Test]
    public function test_multi_match_query(): void
    {
        $book1 = Book::factory()->create([
            'title' => 'Learning Python',
            'description' => 'A comprehensive guide',
        ]);
        $book2 = Book::factory()->create([
            'title' => 'Advanced JavaScript',
            'description' => 'Master Python programming',
        ]);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::multiMatch(['title', 'description'], 'Python'))
            ->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
    }

    #[Test]
    public function test_query_string_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'The Fox and the Hound']);
        $book2 = Book::factory()->create(['title' => 'The Rabbit and the Turtle']);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::queryString('title:fox'))
            ->execute();

        $this->assertSame(1, $result->total);
        $this->assertSame($book1->id, $result->models()->first()->id);
    }

    #[Test]
    public function test_simple_query_string_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'Modern Database Systems']);
        $book2 = Book::factory()->create(['title' => 'Ancient History']);

        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::simpleQueryString('database + modern'))
            ->execute();

        $this->assertSame(1, $result->total);
        $this->assertSame($book1->id, $result->models()->first()->id);
    }
}
