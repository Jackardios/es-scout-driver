<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Illuminate\Database\Eloquent\Model;
use Jackardios\EsScoutDriver\Exceptions\InvalidSearchResultException;
use Jackardios\EsScoutDriver\Search\Hit;
use Jackardios\EsScoutDriver\Search\Paginator;
use Jackardios\EsScoutDriver\Search\SearchResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    #[Test]
    public function it_returns_search_result(): void
    {
        $searchResult = $this->createSearchResult();

        $paginator = new Paginator($searchResult, 10);

        $this->assertSame($searchResult, $paginator->searchResult());
    }

    #[Test]
    public function it_uses_hits_as_default_items(): void
    {
        $searchResult = $this->createSearchResultWithHits(2);

        $paginator = new Paginator($searchResult, 10);

        $items = $paginator->items();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(Hit::class, $items[0]);
        $this->assertInstanceOf(Hit::class, $items[1]);
    }

    #[Test]
    public function it_throws_exception_when_total_is_missing(): void
    {
        $rawResult = [
            'hits' => [
                'hits' => [],
            ],
        ];
        $searchResult = new SearchResult($rawResult);

        $this->expectException(InvalidSearchResultException::class);
        $this->expectExceptionMessage('Search result does not contain the total hits number');

        new Paginator($searchResult, 10);
    }

    #[Test]
    public function it_sets_total_from_search_result(): void
    {
        $searchResult = $this->createSearchResultWithHits(0, 100);

        $paginator = new Paginator($searchResult, 10);

        $this->assertSame(100, $paginator->total());
    }

    #[Test]
    public function it_accepts_per_page_parameter(): void
    {
        $searchResult = $this->createSearchResult();

        $paginator = new Paginator($searchResult, 25);

        $this->assertSame(25, $paginator->perPage());
    }

    #[Test]
    public function it_accepts_current_page_parameter(): void
    {
        $searchResult = $this->createSearchResult();

        $paginator = new Paginator($searchResult, 10, 3);

        $this->assertSame(3, $paginator->currentPage());
    }

    #[Test]
    public function it_accepts_options_parameter(): void
    {
        $searchResult = $this->createSearchResult();

        $paginator = new Paginator($searchResult, 10, 1, ['path' => '/search']);

        $this->assertSame('/search', $paginator->path());
    }

    #[Test]
    public function with_models_returns_new_instance_with_models(): void
    {
        $model = $this->createMock(Model::class);
        $searchResult = $this->createSearchResultWithModels([$model], 1);

        $paginator = new Paginator($searchResult, 10);

        // Initially items are Hit objects
        $this->assertInstanceOf(Hit::class, $paginator->items()[0]);

        $newPaginator = $paginator->withModels();

        // Original paginator should be unchanged
        $this->assertInstanceOf(Hit::class, $paginator->items()[0]);

        // New paginator should have models
        $this->assertNotSame($paginator, $newPaginator);
        $this->assertCount(1, $newPaginator->items());
        $this->assertInstanceOf(Model::class, $newPaginator->items()[0]);
    }

    #[Test]
    public function with_documents_returns_new_instance_with_documents(): void
    {
        $documents = [
            ['title' => 'Book A'],
            ['title' => 'Book B'],
        ];

        $searchResult = $this->createSearchResultWithDocuments($documents, 2);

        $paginator = new Paginator($searchResult, 10);

        // Initially items are Hit objects
        $this->assertInstanceOf(Hit::class, $paginator->items()[0]);

        $newPaginator = $paginator->withDocuments();

        // Original paginator should be unchanged
        $this->assertInstanceOf(Hit::class, $paginator->items()[0]);

        // New paginator should have documents
        $this->assertNotSame($paginator, $newPaginator);
        $items = $newPaginator->items();
        $this->assertCount(2, $items);
        $this->assertSame(['title' => 'Book A'], $items[0]);
        $this->assertSame(['title' => 'Book B'], $items[1]);
    }

    #[Test]
    public function with_models_is_immutable(): void
    {
        $searchResult = $this->createSearchResult();

        $paginator = new Paginator($searchResult, 10);
        $newPaginator = $paginator->withModels();

        $this->assertInstanceOf(Paginator::class, $newPaginator);
        $this->assertNotSame($paginator, $newPaginator);
    }

    #[Test]
    public function with_documents_is_immutable(): void
    {
        $searchResult = $this->createSearchResult();

        $paginator = new Paginator($searchResult, 10);
        $newPaginator = $paginator->withDocuments();

        $this->assertInstanceOf(Paginator::class, $newPaginator);
        $this->assertNotSame($paginator, $newPaginator);
    }

    #[Test]
    public function it_calculates_last_page_correctly(): void
    {
        $searchResult = $this->createSearchResultWithHits(0, 95);

        $paginator = new Paginator($searchResult, 10);

        $this->assertSame(10, $paginator->lastPage());
    }

    #[Test]
    public function it_proxies_calls_to_search_result(): void
    {
        $searchResult = $this->createSearchResultWithAggregations(['my_agg' => ['value' => 42]]);

        $paginator = new Paginator($searchResult, 10);

        $aggregations = $paginator->aggregations();

        $this->assertSame(['my_agg' => ['value' => 42]], $aggregations);
    }

    #[Test]
    public function it_proxies_highlights_to_search_result(): void
    {
        $rawResult = [
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [
                    [
                        '_index' => 'test',
                        '_id' => '1',
                        '_source' => ['title' => 'Test'],
                        'highlight' => ['title' => ['<em>Test</em>']],
                    ],
                ],
            ],
        ];
        $searchResult = new SearchResult($rawResult);

        $paginator = new Paginator($searchResult, 10);

        $highlights = $paginator->highlights();

        $this->assertCount(1, $highlights);
        $this->assertSame(['title' => ['<em>Test</em>']], $highlights->first());
    }

    #[Test]
    public function it_proxies_calls_to_collection(): void
    {
        $searchResult = $this->createSearchResultWithHits(3);

        $paginator = new Paginator($searchResult, 10);

        // pluck is a Collection method
        $ids = $paginator->pluck('documentId');

        $this->assertCount(3, $ids);
        $this->assertSame(['1', '2', '3'], $ids->all());
    }

    private function createSearchResult(): SearchResult
    {
        return new SearchResult([
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
        ]);
    }

    private function createSearchResultWithHits(int $count, ?int $total = null): SearchResult
    {
        $hits = [];
        for ($i = 1; $i <= $count; $i++) {
            $hits[] = [
                '_index' => 'test',
                '_id' => (string) $i,
                '_source' => ['id' => $i],
            ];
        }

        return new SearchResult([
            'hits' => [
                'total' => ['value' => $total ?? $count],
                'hits' => $hits,
            ],
        ]);
    }

    private function createSearchResultWithModels(array $models, int $total): SearchResult
    {
        $hits = [];
        foreach ($models as $index => $model) {
            $hits[] = [
                '_index' => 'test',
                '_id' => (string) ($index + 1),
                '_source' => ['id' => $index + 1],
            ];
        }

        $modelIndex = 0;
        $modelResolver = function (string $indexName, string $documentId) use (&$models, &$modelIndex) {
            return $models[$modelIndex++] ?? null;
        };

        return new SearchResult([
            'hits' => [
                'total' => ['value' => $total],
                'hits' => $hits,
            ],
        ], $modelResolver);
    }

    private function createSearchResultWithDocuments(array $documents, int $total): SearchResult
    {
        $hits = [];
        foreach ($documents as $index => $document) {
            $hits[] = [
                '_index' => 'test',
                '_id' => (string) ($index + 1),
                '_source' => $document,
            ];
        }

        return new SearchResult([
            'hits' => [
                'total' => ['value' => $total],
                'hits' => $hits,
            ],
        ]);
    }

    private function createSearchResultWithAggregations(array $aggregations): SearchResult
    {
        return new SearchResult([
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
            'aggregations' => $aggregations,
        ]);
    }
}
