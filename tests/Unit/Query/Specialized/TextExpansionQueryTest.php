<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Specialized\TextExpansionQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TextExpansionQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_text_expansion_query(): void
    {
        $query = (new TextExpansionQuery('ml.tokens', '.elser_model_2'))
            ->modelText('What is machine learning?');

        $this->assertSame([
            'text_expansion' => [
                'ml.tokens' => [
                    'model_id' => '.elser_model_2',
                    'model_text' => 'What is machine learning?',
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_throws_exception_when_model_text_not_set(): void
    {
        $query = new TextExpansionQuery('ml.tokens', '.elser_model_2');

        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('TextExpansionQuery requires modelText to be set');

        $query->toArray();
    }

    #[Test]
    public function it_builds_text_expansion_query_with_prune(): void
    {
        $query = (new TextExpansionQuery('ml.tokens', '.elser_model_2'))
            ->modelText('search query')
            ->prune(true);

        $result = $query->toArray();
        $this->assertTrue($result['text_expansion']['ml.tokens']['prune']);
    }

    #[Test]
    public function it_builds_text_expansion_query_with_pruning_config(): void
    {
        $query = (new TextExpansionQuery('ml.tokens', '.elser_model_2'))
            ->modelText('search query')
            ->prune(true)
            ->pruningConfig(
                tokensFreqRatioThreshold: 5.0,
                tokensWeightThreshold: 0.4,
                onlyScorePrunedTokens: false,
            );

        $result = $query->toArray();
        $this->assertSame([
            'tokens_freq_ratio_threshold' => 5.0,
            'tokens_weight_threshold' => 0.4,
            'only_score_pruned_tokens' => false,
        ], $result['text_expansion']['ml.tokens']['pruning_config']);
    }

    #[Test]
    public function it_builds_text_expansion_query_with_partial_pruning_config(): void
    {
        $query = (new TextExpansionQuery('ml.tokens', '.elser_model_2'))
            ->modelText('search query')
            ->pruningConfig(tokensWeightThreshold: 0.5);

        $result = $query->toArray();
        $this->assertSame([
            'tokens_weight_threshold' => 0.5,
        ], $result['text_expansion']['ml.tokens']['pruning_config']);
    }

    #[Test]
    public function it_builds_text_expansion_query_with_boost(): void
    {
        $query = (new TextExpansionQuery('ml.tokens', '.elser_model_2'))
            ->modelText('search query')
            ->boost(2.0);

        $result = $query->toArray();
        $this->assertSame(2.0, $result['text_expansion']['ml.tokens']['boost']);
    }

    #[Test]
    public function it_builds_text_expansion_query_with_all_options(): void
    {
        $query = (new TextExpansionQuery('ml.tokens', '.elser_model_2'))
            ->modelText('What is Elasticsearch?')
            ->prune(true)
            ->pruningConfig(tokensFreqRatioThreshold: 5.0)
            ->boost(1.5);

        $this->assertSame([
            'text_expansion' => [
                'ml.tokens' => [
                    'model_id' => '.elser_model_2',
                    'model_text' => 'What is Elasticsearch?',
                    'prune' => true,
                    'pruning_config' => [
                        'tokens_freq_ratio_threshold' => 5.0,
                    ],
                    'boost' => 1.5,
                ],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new TextExpansionQuery('ml.tokens', '.elser_model_2');

        $this->assertSame($query, $query->modelText('text'));
        $this->assertSame($query, $query->prune(true));
        $this->assertSame($query, $query->pruningConfig(tokensWeightThreshold: 0.5));
        $this->assertSame($query, $query->boost(1.0));
    }
}
