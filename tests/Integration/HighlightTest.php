<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class HighlightTest extends TestCase
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
    public function test_highlight_returns_highlighted_fragments(): void
    {
        $book = Book::factory()->create([
            'title' => 'The Great Elasticsearch Guide',
            'description' => 'A comprehensive guide to Elasticsearch',
        ]);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('title', 'Elasticsearch'))
            ->highlight('title')
            ->execute();

        $this->assertSame(1, $result->total);

        $highlights = $result->highlights();
        $this->assertNotEmpty($highlights);

        $firstHighlight = $highlights->first();
        $this->assertArrayHasKey('title', $firstHighlight);

        $highlightedText = $firstHighlight['title'][0];
        $this->assertStringContainsString('<em>', $highlightedText);
        $this->assertStringContainsString('</em>', $highlightedText);
        $this->assertStringContainsString('Elasticsearch', $highlightedText);
    }

    #[Test]
    public function test_highlight_with_custom_tags(): void
    {
        $book = Book::factory()->create([
            'title' => 'Advanced Elasticsearch Techniques',
            'description' => 'Learn advanced Elasticsearch features',
        ]);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('title', 'Elasticsearch'))
            ->highlightRaw([
                'fields' => [
                    'title' => new \stdClass(),
                ],
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
            ])
            ->execute();

        $this->assertSame(1, $result->total);

        $highlights = $result->highlights();
        $this->assertNotEmpty($highlights);

        $firstHighlight = $highlights->first();
        $this->assertArrayHasKey('title', $firstHighlight);

        $highlightedText = $firstHighlight['title'][0];
        $this->assertStringContainsString('<mark>', $highlightedText);
        $this->assertStringContainsString('</mark>', $highlightedText);
        $this->assertStringContainsString('Elasticsearch', $highlightedText);
    }

    #[Test]
    public function test_highlight_multiple_fields(): void
    {
        $book = Book::factory()->create([
            'title' => 'Elasticsearch Basics',
            'description' => 'Introduction to Elasticsearch indexing and search',
        ]);
        $book->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::match('description', 'Elasticsearch'))
            ->highlight('title')
            ->highlight('description')
            ->execute();

        $this->assertSame(1, $result->total);

        $highlights = $result->highlights();
        $this->assertNotEmpty($highlights);

        $firstHighlight = $highlights->first();
        $this->assertArrayHasKey('description', $firstHighlight);

        $highlightedDescription = $firstHighlight['description'][0];
        $this->assertStringContainsString('<em>', $highlightedDescription);
        $this->assertStringContainsString('Elasticsearch', $highlightedDescription);
    }
}
