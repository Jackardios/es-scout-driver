<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Jackardios\EsScoutDriver\Search\SearchResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchResultTest extends TestCase
{
    private function makeRawResponse(array $hits = [], int $total = 0, ?float $maxScore = null): array
    {
        return [
            'hits' => [
                'total' => ['value' => $total],
                'max_score' => $maxScore,
                'hits' => $hits,
            ],
        ];
    }

    #[Test]
    public function it_parses_total_and_max_score(): void
    {
        $result = new SearchResult($this->makeRawResponse(total: 42, maxScore: 1.5));

        $this->assertSame(42, $result->total);
        $this->assertSame(1.5, $result->maxScore);
    }

    #[Test]
    public function it_returns_hits(): void
    {
        $rawHits = [
            ['_index' => 'books', '_id' => '1', '_score' => 1.0, '_source' => ['title' => 'A']],
            ['_index' => 'books', '_id' => '2', '_score' => 0.5, '_source' => ['title' => 'B']],
        ];

        $result = new SearchResult($this->makeRawResponse($rawHits, 2));

        $hits = $result->hits();
        $this->assertCount(2, $hits);
        $this->assertSame('1', $hits[0]->documentId);
        $this->assertSame('2', $hits[1]->documentId);
    }

    #[Test]
    public function it_returns_documents(): void
    {
        $rawHits = [
            ['_index' => 'books', '_id' => '1', '_source' => ['title' => 'A']],
            ['_index' => 'books', '_id' => '2', '_source' => ['title' => 'B']],
        ];

        $result = new SearchResult($this->makeRawResponse($rawHits, 2));

        $docs = $result->documents();
        $this->assertCount(2, $docs);
        $this->assertSame(['title' => 'A'], $docs[0]);
        $this->assertSame(['title' => 'B'], $docs[1]);
    }

    #[Test]
    public function it_returns_highlights(): void
    {
        $rawHits = [
            ['_index' => 'books', '_id' => '1', 'highlight' => ['title' => ['<em>A</em>']]],
            ['_index' => 'books', '_id' => '2'],
        ];

        $result = new SearchResult($this->makeRawResponse($rawHits, 2));

        $highlights = $result->highlights();
        $this->assertCount(1, $highlights);
    }

    #[Test]
    public function it_returns_suggestions(): void
    {
        $raw = [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
            'suggest' => [
                'title-suggest' => [
                    ['text' => 'tset', 'offset' => 0, 'length' => 4, 'options' => [['text' => 'test', 'score' => 0.8]]],
                ],
            ],
        ];

        $result = new SearchResult($raw);
        $suggestions = $result->suggestions();

        $this->assertTrue($suggestions->has('title-suggest'));
        $this->assertCount(1, $suggestions['title-suggest']);
        $this->assertSame('tset', $suggestions['title-suggest'][0]->text);
    }

    #[Test]
    public function it_returns_aggregations(): void
    {
        $raw = [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
            'aggregations' => [
                'genres' => ['buckets' => [['key' => 'fiction', 'doc_count' => 10]]],
            ],
        ];

        $result = new SearchResult($raw);

        $this->assertArrayHasKey('genres', $result->aggregations());
    }

    #[Test]
    public function it_returns_empty_aggregations_when_none(): void
    {
        $result = new SearchResult($this->makeRawResponse());

        $this->assertSame([], $result->aggregations());
    }

    #[Test]
    public function it_returns_single_aggregation(): void
    {
        $raw = [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
            'aggregations' => [
                'genres' => ['buckets' => [['key' => 'fiction', 'doc_count' => 10]]],
                'avg_price' => ['value' => 29.99],
            ],
        ];

        $result = new SearchResult($raw);

        $genres = $result->aggregation('genres');
        $this->assertNotNull($genres);
        $this->assertArrayHasKey('buckets', $genres);

        $avgPrice = $result->aggregation('avg_price');
        $this->assertNotNull($avgPrice);
        $this->assertSame(29.99, $avgPrice['value']);

        $this->assertNull($result->aggregation('nonexistent'));
    }

    #[Test]
    public function it_returns_buckets_from_aggregation(): void
    {
        $raw = [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
            'aggregations' => [
                'genres' => [
                    'buckets' => [
                        ['key' => 'fiction', 'doc_count' => 10],
                        ['key' => 'science', 'doc_count' => 5],
                    ],
                ],
            ],
        ];

        $result = new SearchResult($raw);

        $buckets = $result->buckets('genres');
        $this->assertCount(2, $buckets);
        $this->assertSame('fiction', $buckets[0]['key']);
        $this->assertSame('science', $buckets[1]['key']);

        $emptyBuckets = $result->buckets('nonexistent');
        $this->assertCount(0, $emptyBuckets);
    }

    #[Test]
    public function it_returns_aggregation_value(): void
    {
        $raw = [
            'hits' => ['total' => ['value' => 0], 'hits' => []],
            'aggregations' => [
                'avg_price' => ['value' => 29.99],
                'stats' => ['count' => 100, 'min' => 5.0, 'max' => 100.0, 'avg' => 29.99],
            ],
        ];

        $result = new SearchResult($raw);

        $this->assertSame(29.99, $result->aggregationValue('avg_price'));
        $this->assertSame(100, $result->aggregationValue('stats', 'count'));
        $this->assertSame(5.0, $result->aggregationValue('stats', 'min'));
        $this->assertNull($result->aggregationValue('nonexistent'));
        $this->assertNull($result->aggregationValue('avg_price', 'nonexistent_key'));
    }

    #[Test]
    public function it_is_iterable(): void
    {
        $rawHits = [
            ['_index' => 'books', '_id' => '1', '_source' => ['title' => 'A']],
        ];

        $result = new SearchResult($this->makeRawResponse($rawHits, 1));

        $count = 0;
        foreach ($result as $hit) {
            $count++;
            $this->assertSame('1', $hit->documentId);
        }
        $this->assertSame(1, $count);
    }

    #[Test]
    public function it_exposes_raw_response(): void
    {
        $raw = $this->makeRawResponse(total: 5);
        $result = new SearchResult($raw);

        $this->assertSame($raw, $result->raw);
    }
}
