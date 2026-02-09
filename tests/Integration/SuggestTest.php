<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Search\Suggestion;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class SuggestTest extends TestCase
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
    public function test_term_suggest(): void
    {
        $book1 = Book::factory()->create(['title' => 'Elasticsearch Basics']);
        $book2 = Book::factory()->create(['title' => 'Elasticsearch Advanced']);
        $book3 = Book::factory()->create(['title' => 'Elasticsearch Guide']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        // Search with a misspelled word "Elastcsearch" instead of "Elasticsearch"
        $result = Book::searchQuery(Query::matchAll())
            ->suggest('title_suggestion', [
                'text' => 'Elastcsearch',
                'term' => [
                    'field' => 'title',
                ],
            ])
            ->execute();

        $suggestions = $result->suggestions();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $suggestions);
        $this->assertArrayHasKey('title_suggestion', $suggestions);

        $titleSuggestions = $suggestions['title_suggestion'];
        $this->assertNotEmpty($titleSuggestions);

        // Check that we have suggestions for the misspelled word
        $firstSuggestion = $titleSuggestions[0];
        $this->assertInstanceOf(Suggestion::class, $firstSuggestion);
        $this->assertNotNull($firstSuggestion->text);
        $this->assertIsArray($firstSuggestion->options);
        $this->assertSame('elastcsearch', $firstSuggestion->text);

        if (!empty($firstSuggestion->options)) {
            $this->assertNotEmpty($firstSuggestion->options);
        }
    }

    #[Test]
    public function test_suggest_returns_in_result(): void
    {
        $book = Book::factory()->create(['title' => 'Programming with Python']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->suggest('did_you_mean', [
                'text' => 'Progrmming',
                'term' => [
                    'field' => 'title',
                ],
            ])
            ->execute();

        $suggestions = $result->suggestions();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $suggestions);
        $this->assertArrayHasKey('did_you_mean', $suggestions);
    }

    #[Test]
    public function test_suggest_raw(): void
    {
        $book1 = Book::factory()->create(['title' => 'JavaScript Fundamentals']);
        $book2 = Book::factory()->create(['title' => 'JavaScript Advanced']);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->suggestRaw([
                'my_suggestion' => [
                    'text' => 'Jvascript',
                    'term' => [
                        'field' => 'title',
                    ],
                ],
            ])
            ->execute();

        $suggestions = $result->suggestions();
        $this->assertNotNull($suggestions);
        $this->assertArrayHasKey('my_suggestion', $suggestions);
    }

    #[Test]
    public function test_multiple_suggestions(): void
    {
        $book1 = Book::factory()->create([
            'title' => 'Data Science Fundamentals',
            'description' => 'Learn data science from scratch',
        ]);
        $book2 = Book::factory()->create([
            'title' => 'Data Analysis Techniques',
            'description' => 'Advanced data analysis methods',
        ]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->suggest('title_suggest', [
                'text' => 'Dta',
                'term' => [
                    'field' => 'title',
                ],
            ])
            ->suggest('description_suggest', [
                'text' => 'dta',
                'term' => [
                    'field' => 'description',
                ],
            ])
            ->execute();

        $suggestions = $result->suggestions();
        $this->assertNotNull($suggestions);
        $this->assertArrayHasKey('title_suggest', $suggestions);
        $this->assertArrayHasKey('description_suggest', $suggestions);
    }

    #[Test]
    public function test_phrase_suggester(): void
    {
        $book = Book::factory()->create(['title' => 'Machine Learning Basics']);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->suggest('phrase_suggestion', [
                'text' => 'Mchin Lerning',
                'phrase' => [
                    'field' => 'title',
                ],
            ])
            ->execute();

        $suggestions = $result->suggestions();
        $this->assertNotNull($suggestions);
        $this->assertArrayHasKey('phrase_suggestion', $suggestions);

        $phraseSuggestions = $suggestions['phrase_suggestion'];
        $this->assertNotEmpty($phraseSuggestions);
    }
}
