<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Generator;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Hit>
 */
final class SearchCursor implements IteratorAggregate
{
    private SearchBuilder $builder;
    private int $chunkSize;
    private string $keepAlive;

    public function __construct(SearchBuilder $builder, int $chunkSize = 1000, string $keepAlive = '5m')
    {
        $this->builder = $builder;
        $this->chunkSize = $chunkSize;
        $this->keepAlive = $keepAlive;
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

        $pitId = $engine->openPointInTime($index, $this->keepAlive);

        try {
            $searchAfter = null;
            $hitIndex = 0;

            do {
                $searchBuilder = clone $this->builder;
                $searchBuilder->pointInTime($pitId, $this->keepAlive);
                $searchBuilder->size($this->chunkSize);

                if ($searchBuilder->getSort() === []) {
                    $searchBuilder->sort('_shard_doc', 'asc');
                }

                if ($searchAfter !== null) {
                    $searchBuilder->searchAfter($searchAfter);
                }

                $result = $searchBuilder->execute();
                $hits = $result->hits();

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
        } finally {
            $engine->closePointInTime($pitId);
        }
    }
}
