<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Throwable;
use Traversable;

/**
 * @implements IteratorAggregate<int, Hit>
 */
final class SearchCursor implements IteratorAggregate
{
    private SearchBuilder $builder;
    private int $chunkSize;
    private string $keepAlive;
    private ?string $routing;
    private ?string $preference;

    /** @internal Obtain cursors from SearchBuilder::cursor(). */
    public function __construct(
        SearchBuilder $builder,
        int $chunkSize = 1000,
        string $keepAlive = '5m',
        ?string $routing = null,
        ?string $preference = null,
    ) {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('chunkSize must be greater than 0.');
        }

        $this->builder = $builder;
        $this->chunkSize = $chunkSize;
        $this->keepAlive = $keepAlive;
        $this->routing = $routing;
        $this->preference = $preference;
    }

    public function getIterator(): Traversable
    {
        return $this->iterate();
    }

    /**
     * @return Generator<int, Hit>
     */
    private function iterate(): Generator
    {
        $indexNames = $this->builder->getIndexNames();
        $index = implode(',', array_values($indexNames));
        $engine = $this->builder->getEngine();

        $currentPitId = $engine->openPointInTime($index, $this->keepAlive, $this->routing, $this->preference);
        $primaryError = null;

        try {
            $searchAfter = $this->builder->getSearchAfter();
            $hitIndex = 0;

            do {
                $searchBuilder = clone $this->builder;
                $searchBuilder->pointInTime($currentPitId, $this->keepAlive);
                $searchBuilder->size($this->chunkSize);

                if ($searchBuilder->getTrackTotalHits() === null) {
                    // The cursor does not expose the total; counting it on every page is wasted work.
                    $searchBuilder->trackTotalHits(false);
                }

                if (!$this->hasShardDocSort($searchBuilder->getSort())) {
                    // Ensure deterministic pagination for PIT + search_after
                    $searchBuilder->sort('_shard_doc', 'asc');
                }

                if ($searchAfter !== null) {
                    // Elasticsearch requires from=0 whenever search_after is used.
                    $searchBuilder->from(0);
                    $searchBuilder->searchAfter($searchAfter);
                }

                $result = $searchBuilder->execute();
                $hits = $result->hits();

                $returnedPitId = $result->raw['pit_id'] ?? null;
                if (is_string($returnedPitId) && $returnedPitId !== '') {
                    $currentPitId = $returnedPitId;
                }

                if ($hits->isEmpty()) {
                    break;
                }

                foreach ($hits as $hit) {
                    yield $hitIndex++ => $hit;
                }

                /** @var Hit $lastHit */
                $lastHit = $hits->last();
                $searchAfter = $lastHit->sort ?: null;

            } while ($hits->count() === $this->chunkSize && $searchAfter !== null);
        } catch (Throwable $e) {
            $primaryError = $e;
            throw $e;
        } finally {
            try {
                $engine->closePointInTime($currentPitId);
            } catch (Throwable $closeError) {
                if ($primaryError === null) {
                    throw $closeError;
                }
            }
        }
    }

    private function hasShardDocSort(array $sort): bool
    {
        foreach ($sort as $sortItem) {
            if ($sortItem === '_shard_doc' || (is_array($sortItem) && array_key_exists('_shard_doc', $sortItem))) {
                return true;
            }
        }

        return false;
    }
}
