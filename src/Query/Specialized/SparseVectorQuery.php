<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * Sparse vector query for semantic search using ELSER or other sparse embedding models.
 *
 * Converts query text into a sparse vector using an inference endpoint (inferenceId, or the
 * endpoint of a semantic_text field when it is omitted), or uses a pre-computed sparse vector directly.
 *
 * @since Elasticsearch 8.15 (replaces TextExpansionQuery, deprecated in the same release)
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-sparse-vector-query.html
 */
final class SparseVectorQuery implements QueryInterface
{
    use HasBoost;

    private ?string $inferenceId = null;
    private ?string $query = null;
    /** @var array<string, float>|null */
    private ?array $queryVector = null;
    private ?bool $prune = null;
    /** @var array<string, mixed>|null */
    private ?array $pruningConfig = null;

    public function __construct(
        private string $field,
    ) {}

    public function inferenceId(string $inferenceId): self
    {
        $this->inferenceId = $inferenceId;
        return $this;
    }

    public function query(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    /** @param array<string, float> $queryVector */
    public function queryVector(array $queryVector): self
    {
        $this->queryVector = $queryVector;
        return $this;
    }

    public function prune(bool $prune = true): self
    {
        $this->prune = $prune;
        return $this;
    }

    public function pruningConfig(?float $tokensFreqRatioThreshold = null, ?float $tokensWeightThreshold = null, ?bool $onlyScorePrunedTokens = null): self
    {
        $config = [];
        if ($tokensFreqRatioThreshold !== null) {
            $config['tokens_freq_ratio_threshold'] = $tokensFreqRatioThreshold;
        }
        if ($tokensWeightThreshold !== null) {
            $config['tokens_weight_threshold'] = $tokensWeightThreshold;
        }
        if ($onlyScorePrunedTokens !== null) {
            $config['only_score_pruned_tokens'] = $onlyScorePrunedTokens;
        }
        $this->pruningConfig = $config ?: null;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->queryVector === null && $this->query === null) {
            throw new InvalidQueryException('SparseVectorQuery requires either query or queryVector');
        }

        if ($this->queryVector !== null && ($this->query !== null || $this->inferenceId !== null)) {
            throw new InvalidQueryException(
                'SparseVectorQuery accepts either queryVector or query with an optional inferenceId, not both',
            );
        }

        $params = [
            'field' => $this->field,
        ];

        if ($this->inferenceId !== null) {
            $params['inference_id'] = $this->inferenceId;
        }

        if ($this->query !== null) {
            $params['query'] = $this->query;
        }

        if ($this->queryVector !== null) {
            $params['query_vector'] = $this->queryVector;
        }

        if ($this->prune !== null) {
            $params['prune'] = $this->prune;
        }

        if ($this->pruningConfig !== null) {
            $params['pruning_config'] = $this->pruningConfig;
        }

        $this->applyBoost($params);

        return ['sparse_vector' => $params];
    }
}
