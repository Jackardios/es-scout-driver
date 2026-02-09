<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Tests\App\Book;
use PHPUnit\Framework\Attributes\Test;

final class AggregationTest extends TestCase
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
    public function test_terms_aggregation(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'John Doe']);
        $book4 = Book::factory()->create(['author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();
        $book4->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('authors', Agg::terms('author'))
            ->execute();

        $this->assertSame(4, $result->total);

        $aggregations = $result->aggregations();
        $this->assertArrayHasKey('authors', $aggregations);

        $buckets = $aggregations['authors']['buckets'];
        $this->assertCount(2, $buckets);

        $authorCounts = collect($buckets)->pluck('doc_count', 'key')->toArray();
        $this->assertSame(3, $authorCounts['John Doe']);
        $this->assertSame(1, $authorCounts['Jane Smith']);
    }

    #[Test]
    public function test_avg_aggregation(): void
    {
        $book1 = Book::factory()->create(['price' => 10.00]);
        $book2 = Book::factory()->create(['price' => 20.00]);
        $book3 = Book::factory()->create(['price' => 30.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('avg_price', Agg::avg('price'))
            ->execute();

        $this->assertSame(3, $result->total);

        $aggregations = $result->aggregations();
        $this->assertArrayHasKey('avg_price', $aggregations);
        $this->assertSame(20.0, $aggregations['avg_price']['value']);
    }

    #[Test]
    public function test_aggregation_with_post_filter(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe', 'price' => 10.00]);
        $book2 = Book::factory()->create(['author' => 'Jane Smith', 'price' => 20.00]);
        $book3 = Book::factory()->create(['author' => 'John Doe', 'price' => 30.00]);
        $book4 = Book::factory()->create(['author' => 'Bob Johnson', 'price' => 40.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();
        $book4->searchable();

        $this->refreshIndex('books');

        // Aggregate over all authors, but only return books by John Doe
        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('authors', Agg::terms('author'))
            ->postFilter(Query::term('author', 'John Doe'))
            ->execute();

        // Post-filter affects the returned documents
        $this->assertSame(2, $result->total);
        $ids = $result->models()->pluck('id')->toArray();
        $this->assertContains($book1->id, $ids);
        $this->assertContains($book3->id, $ids);

        // But aggregation is calculated before the post-filter
        $aggregations = $result->aggregations();
        $this->assertArrayHasKey('authors', $aggregations);

        $buckets = $aggregations['authors']['buckets'];
        $this->assertCount(3, $buckets);

        $authorCounts = collect($buckets)->pluck('doc_count', 'key')->toArray();
        $this->assertSame(2, $authorCounts['John Doe']);
        $this->assertSame(1, $authorCounts['Jane Smith']);
        $this->assertSame(1, $authorCounts['Bob Johnson']);
    }

    // ---- Tests for Agg factory ----

    #[Test]
    public function test_agg_terms_with_size(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('authors', Agg::terms('author')->size(10))
            ->execute();

        $buckets = $result->buckets('authors');
        $this->assertCount(2, $buckets);
    }

    #[Test]
    public function test_agg_avg(): void
    {
        $book1 = Book::factory()->create(['price' => 10.00]);
        $book2 = Book::factory()->create(['price' => 20.00]);
        $book3 = Book::factory()->create(['price' => 30.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('avg_price', Agg::avg('price'))
            ->execute();

        $this->assertSame(20.0, $result->aggregationValue('avg_price'));
    }

    #[Test]
    public function test_agg_sum(): void
    {
        $book1 = Book::factory()->create(['price' => 10.00]);
        $book2 = Book::factory()->create(['price' => 20.00]);
        $book3 = Book::factory()->create(['price' => 30.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('total_price', Agg::sum('price'))
            ->execute();

        $this->assertSame(60.0, $result->aggregationValue('total_price'));
    }

    #[Test]
    public function test_agg_min_max(): void
    {
        $book1 = Book::factory()->create(['price' => 10.00]);
        $book2 = Book::factory()->create(['price' => 50.00]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('min_price', Agg::min('price'))
            ->aggregate('max_price', Agg::max('price'))
            ->execute();

        $this->assertSame(10.0, $result->aggregationValue('min_price'));
        $this->assertSame(50.0, $result->aggregationValue('max_price'));
    }

    #[Test]
    public function test_agg_stats(): void
    {
        $book1 = Book::factory()->create(['price' => 10.00]);
        $book2 = Book::factory()->create(['price' => 20.00]);

        $book1->searchable();
        $book2->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('price_stats', Agg::stats('price'))
            ->execute();

        $stats = $result->aggregation('price_stats');
        $this->assertSame(2, $stats['count']);
        $this->assertSame(10.0, $stats['min']);
        $this->assertSame(20.0, $stats['max']);
        $this->assertSame(15.0, $stats['avg']);
        $this->assertSame(30.0, $stats['sum']);
    }

    #[Test]
    public function test_agg_cardinality(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book2 = Book::factory()->create(['author' => 'Jane Smith']);
        $book3 = Book::factory()->create(['author' => 'John Doe']);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('unique_authors', Agg::cardinality('author'))
            ->execute();

        $this->assertSame(2, $result->aggregationValue('unique_authors'));
    }

    #[Test]
    public function test_agg_range(): void
    {
        $book1 = Book::factory()->create(['price' => 5.00]);
        $book2 = Book::factory()->create(['price' => 15.00]);
        $book3 = Book::factory()->create(['price' => 25.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('price_ranges', Agg::range('price')->ranges([
                ['to' => 10],
                ['from' => 10, 'to' => 20],
                ['from' => 20],
            ]))
            ->execute();

        $buckets = $result->buckets('price_ranges');
        $this->assertCount(3, $buckets);

        $bucketCounts = $buckets->pluck('doc_count')->toArray();
        $this->assertSame([1, 1, 1], $bucketCounts);
    }

    #[Test]
    public function test_agg_with_sub_aggregations(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe', 'price' => 10.00]);
        $book2 = Book::factory()->create(['author' => 'John Doe', 'price' => 20.00]);
        $book3 = Book::factory()->create(['author' => 'Jane Smith', 'price' => 30.00]);

        $book1->searchable();
        $book2->searchable();
        $book3->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('by_author', Agg::terms('author')
                ->agg('avg_price', Agg::avg('price')))
            ->execute();

        $buckets = $result->buckets('by_author');
        $this->assertCount(2, $buckets);

        $johnBucket = $buckets->firstWhere('key', 'John Doe');
        $this->assertSame(15.0, $johnBucket['avg_price']['value']);

        $janeBucket = $buckets->firstWhere('key', 'Jane Smith');
        $this->assertSame(30.0, $janeBucket['avg_price']['value']);
    }

    #[Test]
    public function test_aggregate_accepts_raw_array(): void
    {
        $book1 = Book::factory()->create(['author' => 'John Doe']);
        $book1->searchable();

        $this->refreshIndex('books');

        $result = Book::searchQuery(Query::matchAll())
            ->aggregate('authors', ['terms' => ['field' => 'author']])
            ->execute();

        $this->assertArrayHasKey('authors', $result->aggregations());
    }
}
