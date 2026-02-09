<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Jackardios\EsScoutDriver\Search\Hit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HitTest extends TestCase
{
    #[Test]
    public function it_creates_from_raw_hit(): void
    {
        $raw = [
            '_index' => 'books',
            '_id' => '1',
            '_score' => 1.5,
            '_source' => ['title' => 'Test Book'],
            'highlight' => ['title' => ['<em>Test</em> Book']],
            'sort' => [1],
        ];

        $hit = Hit::fromRaw($raw);

        $this->assertSame('books', $hit->indexName);
        $this->assertSame('1', $hit->documentId);
        $this->assertSame(1.5, $hit->score);
        $this->assertSame(['title' => 'Test Book'], $hit->source);
        $this->assertSame(['title' => ['<em>Test</em> Book']], $hit->highlight);
        $this->assertSame([1], $hit->sort);
    }

    #[Test]
    public function it_handles_missing_optional_fields(): void
    {
        $hit = Hit::fromRaw(['_index' => 'books', '_id' => '1']);

        $this->assertNull($hit->score);
        $this->assertSame([], $hit->source);
        $this->assertSame([], $hit->highlight);
        $this->assertSame([], $hit->sort);
    }

    #[Test]
    public function it_resolves_model_lazily(): void
    {
        $callCount = 0;
        $resolver = function (string $index, string $id) use (&$callCount) {
            $callCount++;
            return null;
        };

        $hit = Hit::fromRaw(['_index' => 'books', '_id' => '1'], $resolver);

        $this->assertSame(0, $callCount);
        $hit->model();
        $this->assertSame(1, $callCount);
        $hit->model(); // second call should not invoke resolver again
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function it_returns_null_model_without_resolver(): void
    {
        $hit = Hit::fromRaw(['_index' => 'books', '_id' => '1']);

        $this->assertNull($hit->model());
    }

    #[Test]
    public function it_parses_inner_hits(): void
    {
        $hit = Hit::fromRaw([
            '_index' => 'books',
            '_id' => '1',
            'inner_hits' => [
                'chapters' => [
                    'hits' => [
                        'hits' => [
                            ['_index' => 'books', '_id' => '1', '_score' => 0.5, '_source' => ['chapter' => 1]],
                            ['_index' => 'books', '_id' => '1', '_score' => 0.3, '_source' => ['chapter' => 2]],
                        ],
                    ],
                ],
            ],
        ]);

        $innerHits = $hit->innerHits();
        $this->assertCount(1, $innerHits); // 1 group: 'chapters'
        $this->assertTrue($innerHits->has('chapters'));

        $chapters = $innerHits['chapters'];
        $this->assertCount(2, $chapters);
        $this->assertSame(['chapter' => 1], $chapters[0]->source);
        $this->assertSame(['chapter' => 2], $chapters[1]->source);
    }

    #[Test]
    public function it_returns_empty_inner_hits_when_none_present(): void
    {
        $hit = Hit::fromRaw(['_index' => 'books', '_id' => '1']);

        $this->assertCount(0, $hit->innerHits());
    }

    #[Test]
    public function it_caches_inner_hits(): void
    {
        $hit = Hit::fromRaw([
            '_index' => 'books',
            '_id' => '1',
            'inner_hits' => [
                'chapters' => [
                    'hits' => [
                        'hits' => [
                            ['_index' => 'books', '_id' => '1', '_source' => ['chapter' => 1]],
                        ],
                    ],
                ],
            ],
        ]);

        $innerHits1 = $hit->innerHits();
        $innerHits2 = $hit->innerHits();

        $this->assertSame($innerHits1, $innerHits2);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $hit = Hit::fromRaw([
            '_index' => 'books',
            '_id' => '1',
            '_score' => 1.0,
            '_source' => ['title' => 'Test'],
            '_explanation' => ['value' => 1.0, 'description' => 'test'],
        ]);

        $array = $hit->toArray();
        $this->assertSame('books', $array['index_name']);
        $this->assertSame('1', $array['document_id']);
        $this->assertSame(1.0, $array['score']);
        $this->assertSame(['title' => 'Test'], $array['source']);
        $this->assertArrayHasKey('explanation', $array);
        $this->assertSame(['value' => 1.0, 'description' => 'test'], $array['explanation']);
    }

    #[Test]
    public function it_includes_explanation_in_to_array(): void
    {
        $hit = Hit::fromRaw([
            '_index' => 'books',
            '_id' => '1',
            '_explanation' => [
                'value' => 1.5,
                'description' => 'weight(title:test in 0)',
                'details' => [],
            ],
        ]);

        $array = $hit->toArray();

        $this->assertArrayHasKey('explanation', $array);
        $this->assertSame([
            'value' => 1.5,
            'description' => 'weight(title:test in 0)',
            'details' => [],
        ], $array['explanation']);
    }
}
