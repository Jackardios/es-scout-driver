<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Jackardios\EsScoutDriver\Search\Suggestion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SuggestionTest extends TestCase
{
    #[Test]
    public function it_creates_from_raw(): void
    {
        $raw = [
            'text' => 'tset',
            'offset' => 0,
            'length' => 4,
            'options' => [
                ['text' => 'test', 'score' => 0.8, 'freq' => 5],
            ],
        ];

        $suggestion = Suggestion::fromRaw($raw);

        $this->assertSame('tset', $suggestion->text);
        $this->assertSame(0, $suggestion->offset);
        $this->assertSame(4, $suggestion->length);
        $this->assertCount(1, $suggestion->options);
        $this->assertSame('test', $suggestion->options[0]['text']);
    }

    #[Test]
    public function it_handles_missing_fields(): void
    {
        $suggestion = Suggestion::fromRaw([]);

        $this->assertSame('', $suggestion->text);
        $this->assertSame(0, $suggestion->offset);
        $this->assertSame(0, $suggestion->length);
        $this->assertSame([], $suggestion->options);
    }

    #[Test]
    public function it_returns_texts(): void
    {
        $suggestion = Suggestion::fromRaw([
            'text' => 'tset',
            'offset' => 0,
            'length' => 4,
            'options' => [
                ['text' => 'test', '_score' => 0.8],
                ['text' => 'best', '_score' => 0.7],
            ],
        ]);

        $texts = $suggestion->texts();

        $this->assertCount(2, $texts);
        $this->assertSame('test', $texts[0]);
        $this->assertSame('best', $texts[1]);
    }

    #[Test]
    public function it_keeps_zero_like_text_values(): void
    {
        $suggestion = Suggestion::fromRaw([
            'text' => '0',
            'offset' => 0,
            'length' => 1,
            'options' => [
                ['text' => '0', '_score' => 1.0],
                ['text' => null, '_score' => 0.5],
            ],
        ]);

        $texts = $suggestion->texts();

        $this->assertCount(1, $texts);
        $this->assertSame('0', $texts[0]);
    }

    #[Test]
    public function it_returns_scores(): void
    {
        $suggestion = Suggestion::fromRaw([
            'text' => 'tset',
            'offset' => 0,
            'length' => 4,
            'options' => [
                ['text' => 'test', '_score' => 0.8],
                ['text' => 'best', '_score' => 0.7],
            ],
        ]);

        $scores = $suggestion->scores();

        $this->assertCount(2, $scores);
        $this->assertSame(0.8, $scores[0]);
        $this->assertSame(0.7, $scores[1]);
    }

    #[Test]
    public function it_caches_texts(): void
    {
        $suggestion = Suggestion::fromRaw([
            'text' => 'tset',
            'offset' => 0,
            'length' => 4,
            'options' => [
                ['text' => 'test', '_score' => 0.8],
            ],
        ]);

        $texts1 = $suggestion->texts();
        $texts2 = $suggestion->texts();

        $this->assertSame($texts1, $texts2);
    }

    #[Test]
    public function it_caches_scores(): void
    {
        $suggestion = Suggestion::fromRaw([
            'text' => 'tset',
            'offset' => 0,
            'length' => 4,
            'options' => [
                ['text' => 'test', '_score' => 0.8],
            ],
        ]);

        $scores1 = $suggestion->scores();
        $scores2 = $suggestion->scores();

        $this->assertSame($scores1, $scores2);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $raw = [
            'text' => 'tset',
            'offset' => 0,
            'length' => 4,
            'options' => [
                ['text' => 'test', '_score' => 0.8],
            ],
        ];

        $suggestion = Suggestion::fromRaw($raw);
        $array = $suggestion->toArray();

        $this->assertSame('tset', $array['text']);
        $this->assertSame(0, $array['offset']);
        $this->assertSame(4, $array['length']);
        $this->assertCount(1, $array['options']);
        $this->assertSame('test', $array['options'][0]['text']);
    }

    #[Test]
    public function it_includes_zero_scores(): void
    {
        $suggestion = Suggestion::fromRaw([
            'text' => 'tset',
            'offset' => 0,
            'length' => 4,
            'options' => [
                ['text' => 'test', '_score' => 0.0],
                ['text' => 'best', '_score' => 0.5],
                ['text' => 'rest', '_score' => null],
            ],
        ]);

        $scores = $suggestion->scores();

        // Should include 0.0 but exclude null
        $this->assertCount(2, $scores);
        $this->assertSame(0.0, $scores[0]);
        $this->assertSame(0.5, $scores[1]);
    }

    #[Test]
    public function it_filters_null_scores_only(): void
    {
        $suggestion = Suggestion::fromRaw([
            'text' => 'tset',
            'offset' => 0,
            'length' => 4,
            'options' => [
                ['text' => 'test'],
                ['text' => 'best', '_score' => null],
            ],
        ]);

        $scores = $suggestion->scores();

        $this->assertCount(0, $scores);
    }
}
