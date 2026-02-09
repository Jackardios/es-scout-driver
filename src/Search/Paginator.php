<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Traits\ForwardsCalls;
use Jackardios\EsScoutDriver\Exceptions\InvalidSearchResultException;

final class Paginator extends LengthAwarePaginator
{
    use ForwardsCalls;

    private SearchResult $searchResult;

    public function __construct(
        SearchResult $searchResult,
        int $perPage,
        ?int $currentPage = null,
        array $options = [],
    ) {
        $this->searchResult = $searchResult;

        if (!isset($searchResult->raw['hits']['total']['value'])) {
            throw InvalidSearchResultException::missingTotalHits();
        }

        parent::__construct(
            $searchResult->hits()->all(),
            $searchResult->total,
            $perPage,
            $currentPage,
            $options,
        );
    }

    public function searchResult(): SearchResult
    {
        return $this->searchResult;
    }

    public function withModels(): self
    {
        $clone = clone $this;
        $clone->items = $this->searchResult->models();
        return $clone;
    }

    public function withDocuments(): self
    {
        $clone = clone $this;
        $clone->items = $this->searchResult->documents();
        return $clone;
    }

    /**
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->getCollection(), $method)) {
            return $this->forwardCallTo($this->getCollection(), $method, $parameters);
        }

        return $this->forwardCallTo($this->searchResult, $method, $parameters);
    }
}
