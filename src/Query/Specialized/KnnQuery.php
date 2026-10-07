<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\SubQuery;

/**
 * K-nearest neighbors (kNN) vector search query.
 *
 * Finds the k nearest vectors to a query vector, as measured by a similarity metric.
 *
 * The knn query exists from Elasticsearch 8.12, but takes `k` only from 8.15. On 8.12–8.14 leave `$k` out: the query
 * collects num_candidates per shard and the size of the search decides how many hits come back; on 8.12 call
 * numCandidates(), which that version requires. From 8.15 a query without `$k` is still valid: Elasticsearch then
 * defaults k to num_candidates, and num_candidates to 1.5 times the size of the search.
 *
 * @since Elasticsearch 8.12 without k, 8.15 with k
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-knn-query.html
 */
final class KnnQuery implements QueryInterface
{
    use HasBoost;

    private const MAX_NUM_CANDIDATES = 10000;

    private ?int $numCandidates = null;
    private ?float $similarity = null;
    private QueryInterface|array|null $filter = null;

    /** @param array<int, float> $queryVector */
    public function __construct(
        private string $field,
        private array $queryVector,
        private ?int $k = null,
    ) {
        if ($k !== null && $k <= 0) {
            throw new InvalidQueryException('KnnQuery requires k to be greater than 0');
        }

        if ($queryVector === []) {
            throw new InvalidQueryException('KnnQuery requires a non-empty query vector');
        }
    }

    public function numCandidates(int $numCandidates): self
    {
        if ($numCandidates <= 0) {
            throw new InvalidQueryException('KnnQuery requires numCandidates to be greater than 0');
        }

        if ($numCandidates > self::MAX_NUM_CANDIDATES) {
            throw new InvalidQueryException('KnnQuery requires numCandidates to be at most 10000');
        }

        if ($this->k !== null && $numCandidates < $this->k) {
            throw new InvalidQueryException('KnnQuery requires numCandidates to be greater than or equal to k');
        }

        $this->numCandidates = $numCandidates;
        return $this;
    }

    public function similarity(float $similarity): self
    {
        $this->similarity = $similarity;
        return $this;
    }

    public function filter(QueryInterface|array $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $params = [
            'field' => $this->field,
            'query_vector' => array_values($this->queryVector),
        ];

        if ($this->k !== null) {
            $params['k'] = $this->k;
        }

        $numCandidates = $this->numCandidates ?? $this->defaultNumCandidates();
        if ($numCandidates !== null) {
            $params['num_candidates'] = $numCandidates;
        }

        if ($this->similarity !== null) {
            $params['similarity'] = $this->similarity;
        }

        if ($this->filter !== null) {
            $params['filter'] = SubQuery::toArray($this->filter);
        }

        $this->applyBoost($params);

        return ['knn' => $params];
    }

    /** Twice k and at least 100, within the 10000 Elasticsearch allows; none without a k or for a k above that. */
    private function defaultNumCandidates(): ?int
    {
        if ($this->k === null || $this->k > self::MAX_NUM_CANDIDATES) {
            return null;
        }

        return min(max($this->k * 2, 100), self::MAX_NUM_CANDIDATES);
    }

    public function __clone(): void
    {
        if ($this->filter instanceof QueryInterface) {
            $this->filter = clone $this->filter;
        }
    }
}
