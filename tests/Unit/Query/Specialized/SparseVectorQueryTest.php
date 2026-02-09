<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Specialized\SparseVectorQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SparseVectorQueryTest extends TestCase
{
    #[Test]
    public function it_builds_sparse_vector_query_with_inference(): void
    {
        $query = (new SparseVectorQuery('ml.tokens'))
            ->inferenceId('my-elser-model')
            ->query('How to avoid muscle soreness?');

        $this->assertSame([
            'sparse_vector' => [
                'field' => 'ml.tokens',
                'inference_id' => 'my-elser-model',
                'query' => 'How to avoid muscle soreness?',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_sparse_vector_query_with_query_vector(): void
    {
        $query = (new SparseVectorQuery('ml.tokens'))
            ->queryVector(['token1' => 0.5, 'token2' => 0.3]);

        $this->assertSame([
            'sparse_vector' => [
                'field' => 'ml.tokens',
                'query_vector' => ['token1' => 0.5, 'token2' => 0.3],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_sparse_vector_query_with_all_options(): void
    {
        $query = (new SparseVectorQuery('ml.tokens'))
            ->inferenceId('my-elser-model')
            ->query('semantic search query')
            ->prune(true)
            ->pruningConfig(5.0, 0.4, true)
            ->boost(2.0);

        $this->assertSame([
            'sparse_vector' => [
                'field' => 'ml.tokens',
                'inference_id' => 'my-elser-model',
                'query' => 'semantic search query',
                'prune' => true,
                'pruning_config' => [
                    'tokens_freq_ratio_threshold' => 5.0,
                    'tokens_weight_threshold' => 0.4,
                    'only_score_pruned_tokens' => true,
                ],
                'boost' => 2.0,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new SparseVectorQuery('ml.tokens');

        $this->assertSame($query, $query->inferenceId('my-model'));
        $this->assertSame($query, $query->query('search'));
        $this->assertSame($query, $query->queryVector(['t' => 0.1]));
        $this->assertSame($query, $query->prune(true));
        $this->assertSame($query, $query->pruningConfig(5.0));
        $this->assertSame($query, $query->boost(1.5));
    }
}
