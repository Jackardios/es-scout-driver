<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Query\Specialized\PinnedQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PinnedQueryTest extends TestCase
{
    #[Test]
    public function it_builds_pinned_query_with_ids(): void
    {
        $query = (new PinnedQuery(new MatchQuery('title', 'apple')))
            ->ids(['1', '4', '100']);

        $this->assertSame([
            'pinned' => [
                'organic' => [
                    'match' => [
                        'title' => [
                            'query' => 'apple',
                        ],
                    ],
                ],
                'ids' => ['1', '4', '100'],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_pinned_query_with_docs(): void
    {
        $organic = ['match_all' => new \stdClass()];
        $query = (new PinnedQuery($organic))
            ->docs([
                ['_index' => 'my-index', '_id' => '1'],
                ['_index' => 'my-index', '_id' => '4'],
            ]);

        $this->assertSame([
            'pinned' => [
                'organic' => $organic,
                'docs' => [
                    ['_index' => 'my-index', '_id' => '1'],
                    ['_index' => 'my-index', '_id' => '4'],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_pinned_query_with_doc_helper(): void
    {
        $query = (new PinnedQuery(new MatchQuery('content', 'search')))
            ->doc('index-a', '1')
            ->doc('index-b', '2');

        $this->assertSame([
            'pinned' => [
                'organic' => [
                    'match' => [
                        'content' => [
                            'query' => 'search',
                        ],
                    ],
                ],
                'docs' => [
                    ['_index' => 'index-a', '_id' => '1'],
                    ['_index' => 'index-b', '_id' => '2'],
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_pinned_query_with_raw_organic(): void
    {
        $query = (new PinnedQuery([
            'bool' => [
                'must' => [
                    ['match' => ['title' => 'test']],
                ],
            ],
        ]))->ids(['1', '2']);

        $this->assertSame([
            'pinned' => [
                'organic' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['title' => 'test']],
                        ],
                    ],
                ],
                'ids' => ['1', '2'],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_pinned_query_with_boost(): void
    {
        $query = (new PinnedQuery(new MatchQuery('title', 'apple')))
            ->ids(['1', '2'])
            ->boost(2.0);

        $this->assertSame([
            'pinned' => [
                'organic' => [
                    'match' => [
                        'title' => [
                            'query' => 'apple',
                        ],
                    ],
                ],
                'ids' => ['1', '2'],
                'boost' => 2.0,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new PinnedQuery(['match_all' => []]);

        $this->assertSame($query, $query->ids(['1']));
        $this->assertSame($query, $query->docs([['_index' => 'i', '_id' => 'x']]));
        $this->assertSame($query, $query->doc('idx', 'id'));
        $this->assertSame($query, $query->boost(2.0));
    }
}
