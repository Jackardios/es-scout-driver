<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasInnerHits;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * K-nearest neighbors (kNN) vector search query.
 *
 * Finds the k nearest vectors to a query vector, as measured by a similarity metric.
 *
 * @since Elasticsearch 8.8
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-knn-query.html
 */
final class KnnQuery implements QueryInterface
{
    use HasBoost;
    use HasInnerHits;

    private ?int $numCandidates = null;
    private ?float $similarity = null;
    private QueryInterface|array|null $filter = null;

    /** @param array<int, float> $queryVector */
    public function __construct(
        private string $field,
        private array $queryVector,
        private int $k,
    ) {
        if ($k <= 0) {
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

        if ($numCandidates < $this->k) {
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
            'query_vector' => $this->queryVector,
            'k' => $this->k,
            'num_candidates' => $this->numCandidates ?? max($this->k * 2, 100),
        ];

        if ($this->similarity !== null) {
            $params['similarity'] = $this->similarity;
        }

        if ($this->filter !== null) {
            $params['filter'] = $this->filter instanceof QueryInterface
                ? $this->filter->toArray()
                : $this->filter;
        }

        $this->applyInnerHits($params);
        $this->applyBoost($params);

        return ['knn' => $params];
    }

    public function __clone(): void
    {
        if ($this->filter instanceof QueryInterface) {
            $this->filter = clone $this->filter;
        }
    }
}
