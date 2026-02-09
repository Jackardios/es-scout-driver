<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

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

    public function toArray(): array
    {
        $hasInference = $this->inferenceId !== null && $this->query !== null;
        $hasQueryVector = $this->queryVector !== null;

        if (!$hasInference && !$hasQueryVector) {
            throw new InvalidQueryException(
                'SparseVectorQuery requires either inferenceId+query or queryVector',
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
