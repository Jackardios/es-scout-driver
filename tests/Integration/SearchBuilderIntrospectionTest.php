<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class SearchBuilderIntrospectionTest extends TestCase
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
    public function test_build_params_reflects_configuration(): void
    {
        $book1 = Book::factory()->create(['title' => 'Test Book', 'author' => 'John Doe', 'price' => 29.99]);
        $book1->searchable();
        $this->refreshIndex('books');

        $builder = Book::searchQuery(Query::matchAll())
            ->sort('price', 'asc')
            ->size(10)
            ->source(['title', 'author'])
            ->aggregate('avg_price', ['avg' => ['field' => 'price']]);

        $params = $builder->buildParams();

        $this->assertArrayHasKey('index', $params);
        $this->assertSame('books', $params['index']);
        $this->assertArrayHasKey('body', $params);
        $this->assertArrayHasKey('query', $params['body']);
        $this->assertArrayHasKey('sort', $params['body']);
        $this->assertArrayHasKey('size', $params['body']);
        $this->assertSame(10, $params['body']['size']);
        $this->assertArrayHasKey('_source', $params['body']);
        $this->assertSame(['title', 'author'], $params['body']['_source']);
        $this->assertArrayHasKey('aggs', $params['body']);
        $this->assertArrayHasKey('avg_price', $params['body']['aggs']);

        $result = $builder->execute();
        $this->assertSame(1, $result->total);
    }

    #[Test]
    public function test_introspection_getters(): void
    {
        $book1 = Book::factory()->create(['title' => 'Test Book', 'author' => 'John Doe']);
        $book1->searchable();
        $this->refreshIndex('books');

        $builder = Book::searchQuery(Query::matchAll())
            ->sort('author', 'asc')
            ->from(5)
            ->size(20)
            ->source(['title'])
            ->highlight('title')
            ->aggregate('authors', ['terms' => ['field' => 'author']]);

        $this->assertSame([['author' => 'asc']], $builder->getSort());
        $this->assertSame(5, $builder->getFrom());
        $this->assertSame(20, $builder->getSize());
        $this->assertSame(['title'], $builder->getSource());
        $this->assertArrayHasKey('fields', $builder->getHighlight());
        $this->assertArrayHasKey('authors', $builder->getAggregations());

        $result = $builder->execute();
        $this->assertSame(1, $result->total);
    }

    #[Test]
    public function test_bool_query_introspection(): void
    {
        $book1 = Book::factory()->create(['title' => 'Great Book', 'author' => 'John Doe', 'tags' => ['fiction']]);
        $book2 = Book::factory()->create(['title' => 'Another Book', 'author' => 'Jane Smith', 'tags' => ['non-fiction']]);
        $book1->searchable();
        $book2->searchable();
        $this->refreshIndex('books');

        $builder = Book::searchQuery(Query::matchAll())
            ->must(Query::match('title', 'book'))
            ->filter(Query::term('author', 'John Doe'));

        $boolQuery = $builder->boolQuery();

        $this->assertTrue($boolQuery->hasClauses());

        $mustClauses = $boolQuery->getMustClauses();
        $this->assertCount(1, $mustClauses);

        $filterClauses = $boolQuery->getFilterClauses();
        $this->assertCount(1, $filterClauses);

        $shouldClauses = $boolQuery->getShouldClauses();
        $this->assertCount(0, $shouldClauses);

        $mustNotClauses = $boolQuery->getMustNotClauses();
        $this->assertCount(0, $mustNotClauses);

        $result = $builder->execute();
        $this->assertSame(1, $result->total);
        $this->assertSame($book1->id, $result->models()->first()->id);
    }

    #[Test]
    public function test_to_json_returns_valid_json(): void
    {
        $builder = Book::searchQuery(Query::matchAll())
            ->sort('author', 'asc')
            ->size(10);

        $json = $builder->toJson();

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('index', $decoded);
        $this->assertArrayHasKey('body', $decoded);
        $this->assertSame(10, $decoded['body']['size']);
    }

    #[Test]
    public function test_to_array_returns_params_array(): void
    {
        $builder = Book::searchQuery(Query::matchAll())
            ->filter(Query::term('author', 'John Doe'))
            ->size(5);

        $params = $builder->toArray();

        $this->assertIsArray($params);
        $this->assertArrayHasKey('index', $params);
        $this->assertArrayHasKey('body', $params);
        $this->assertSame(5, $params['body']['size']);
        $this->assertArrayHasKey('query', $params['body']);
    }

    #[Test]
    public function test_clear_indices_boost(): void
    {
        $builder = Book::searchQuery(Query::matchAll());

        // buildParams should not have indices_boost initially
        $params = $builder->toArray();
        $this->assertArrayNotHasKey('indices_boost', $params['body'] ?? []);

        // Clear should be safe to call even when empty
        $builder->clearIndicesBoost();
        $params = $builder->toArray();
        $this->assertArrayNotHasKey('indices_boost', $params['body'] ?? []);
    }
}
