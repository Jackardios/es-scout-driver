<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class CompoundQueryTest extends TestCase
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
    public function test_function_score_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'First Book', 'author' => 'John Doe']);
        $book2 = Book::factory()->create(['title' => 'Second Book', 'author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['title' => 'Third Book', 'author' => 'Bob Johnson']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(
            Query::functionScore()
                ->query(Query::matchAll())
                ->addFunction(['random_score' => ['seed' => 42, 'field' => '_seq_no']])
                ->functionScoreMode('sum')
                ->boostMode('replace')
        )->execute();

        $this->assertSame(3, $result->total);
        $this->assertNotNull($result->maxScore);
        $this->assertCount(3, $result->models());
    }

    #[Test]
    public function test_dis_max_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'Testing Guide', 'description' => 'A comprehensive book about software']);
        $book2 = Book::factory()->create(['title' => 'PHP Programming', 'description' => 'Learn testing in PHP']);
        $book3 = Book::factory()->create(['title' => 'Java Basics', 'description' => 'Introduction to Java']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(
            Query::disMax()
                ->add(Query::match('title', 'testing'))
                ->add(Query::match('description', 'testing'))
                ->tieBreaker(0.3)
        )->execute();

        $this->assertSame(2, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertNotContains($book3->id, $ids);
    }

    #[Test]
    public function test_boosting_query(): void
    {
        $book1 = Book::factory()->create(['title' => 'Great Book', 'tags' => ['fiction']]);
        $book2 = Book::factory()->create(['title' => 'Boring Manual', 'tags' => ['boring']]);
        $book3 = Book::factory()->create(['title' => 'Awesome Story', 'tags' => ['fiction']]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(
            Query::boosting(Query::matchAll(), Query::term('tags', 'boring'))
                ->negativeBoost(0.5)
        )->execute();

        $this->assertSame(3, $result->total);

        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book2->id, $ids);
        $this->assertContains($book3->id, $ids);
    }
}
