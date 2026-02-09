<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Concerns\HasAnalyzer;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasMinimumShouldMatch;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class MoreLikeThisQuery implements QueryInterface
{
    use HasAnalyzer;
    use HasBoost;
    use HasMinimumShouldMatch;

    /** @var string|array<int, string|array<string, mixed>>|null */
    private string|array|null $unlike = null;
    /** @var array<int, string>|null */
    private ?array $stopWords = null;
    private ?bool $include = null;
    private ?float $boostTerms = null;
    private ?bool $failOnUnsupportedField = null;
    private ?int $minTermFreq = null;
    private ?int $maxQueryTerms = null;
    private ?int $minDocFreq = null;
    private ?int $maxDocFreq = null;
    private ?int $minWordLength = null;
    private ?int $maxWordLength = null;

    /**
     * @param array<int, string> $fields
     * @param string|array<int, string|array<string, mixed>> $like
     */
    public function __construct(
        private array $fields,
        private string|array $like,
    ) {}

    /** @param string|array<int, string|array<string, mixed>> $unlike */
    public function unlike(string|array $unlike): self
    {
        $this->unlike = $unlike;
        return $this;
    }

    /** @param array<int, string> $stopWords */
    public function stopWords(array $stopWords): self
    {
        $this->stopWords = $stopWords;
        return $this;
    }

    public function include(bool $include = true): self
    {
        $this->include = $include;
        return $this;
    }

    public function boostTerms(float $boostTerms): self
    {
        $this->boostTerms = $boostTerms;
        return $this;
    }

    public function failOnUnsupportedField(bool $failOnUnsupportedField = true): self
    {
        $this->failOnUnsupportedField = $failOnUnsupportedField;
        return $this;
    }

    public function minTermFreq(int $minTermFreq): self
    {
        $this->minTermFreq = $minTermFreq;
        return $this;
    }

    public function maxQueryTerms(int $maxQueryTerms): self
    {
        $this->maxQueryTerms = $maxQueryTerms;
        return $this;
    }

    public function minDocFreq(int $minDocFreq): self
    {
        $this->minDocFreq = $minDocFreq;
        return $this;
    }

    public function maxDocFreq(int $maxDocFreq): self
    {
        $this->maxDocFreq = $maxDocFreq;
        return $this;
    }

    public function minWordLength(int $minWordLength): self
    {
        $this->minWordLength = $minWordLength;
        return $this;
    }

    public function maxWordLength(int $maxWordLength): self
    {
        $this->maxWordLength = $maxWordLength;
        return $this;
    }

    public function toArray(): array
    {
        $params = [
            'fields' => $this->fields,
            'like' => $this->like,
        ];

        if ($this->unlike !== null) {
            $params['unlike'] = $this->unlike;
        }

        if ($this->minTermFreq !== null) {
            $params['min_term_freq'] = $this->minTermFreq;
        }

        if ($this->maxQueryTerms !== null) {
            $params['max_query_terms'] = $this->maxQueryTerms;
        }

        if ($this->minDocFreq !== null) {
            $params['min_doc_freq'] = $this->minDocFreq;
        }

        if ($this->maxDocFreq !== null) {
            $params['max_doc_freq'] = $this->maxDocFreq;
        }

        if ($this->minWordLength !== null) {
            $params['min_word_length'] = $this->minWordLength;
        }

        if ($this->maxWordLength !== null) {
            $params['max_word_length'] = $this->maxWordLength;
        }

        if ($this->stopWords !== null) {
            $params['stop_words'] = $this->stopWords;
        }

        if ($this->include !== null) {
            $params['include'] = $this->include;
        }

        if ($this->boostTerms !== null) {
            $params['boost_terms'] = $this->boostTerms;
        }

        if ($this->failOnUnsupportedField !== null) {
            $params['fail_on_unsupported_field'] = $this->failOnUnsupportedField;
        }

        $this->applyAnalyzer($params);
        $this->applyMinimumShouldMatch($params);
        $this->applyBoost($params);

        return ['more_like_this' => $params];
    }
}
