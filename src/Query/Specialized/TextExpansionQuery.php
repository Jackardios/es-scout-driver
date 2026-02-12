<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * Text expansion query for rank features or sparse vectors.
 *
 * Uses a natural language processing (NLP) model to convert the query
 * text into a list of token-weight pairs which are used in a weighted
 * token query against a sparse vector or rank features field.
 *
 * @since Elasticsearch 8.8
 * @deprecated Since Elasticsearch 8.15. Use {@see SparseVectorQuery} instead.
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-text-expansion-query.html
 */
final class TextExpansionQuery implements QueryInterface
{
    use HasBoost;

    private ?string $modelText = null;
    private ?bool $prune = null;
    /** @var array<string, mixed>|null */
    private ?array $pruningConfig = null;

    public function __construct(
        private string $field,
        private string $modelId,
    ) {}

    public function modelText(string $modelText): self
    {
        $this->modelText = $modelText;
        return $this;
    }

    public function prune(bool $prune = true): self
    {
        $this->prune = $prune;
        return $this;
    }

    public function pruningConfig(
        ?float $tokensFreqRatioThreshold = null,
        ?float $tokensWeightThreshold = null,
        ?bool $onlyScorePrunedTokens = null,
    ): self {
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
        if ($this->modelText === null) {
            throw new InvalidQueryException('TextExpansionQuery requires modelText to be set');
        }

        $params = [
            'model_id' => $this->modelId,
            'model_text' => $this->modelText,
        ];

        if ($this->prune !== null) {
            $params['prune'] = $this->prune;
        }

        if ($this->pruningConfig !== null) {
            $params['pruning_config'] = $this->pruningConfig;
        }

        $this->applyBoost($params);

        return ['text_expansion' => [$this->field => $params]];
    }
}
