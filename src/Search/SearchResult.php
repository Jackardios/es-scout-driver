<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Hit>
 */
final class SearchResult implements IteratorAggregate
{
    public readonly int $total;
    public readonly ?float $maxScore;

    private ?Closure $modelResolver;

    /** @var Collection<int, Hit>|null */
    private ?Collection $parsedHits = null;

    /** @var Collection<string, Collection<int, Suggestion>>|null */
    private ?Collection $parsedSuggestions = null;

    public function __construct(
        public readonly array $raw,
        ?Closure $modelResolver = null,
    ) {
        $this->modelResolver = $modelResolver;
        $this->total = $raw['hits']['total']['value'] ?? 0;
        $this->maxScore = $raw['hits']['max_score'] ?? null;
    }

    /** @return Collection<int, Hit> */
    public function hits(): Collection
    {
        if ($this->parsedHits === null) {
            $this->parsedHits = Collection::make($this->raw['hits']['hits'] ?? [])
                ->map(fn(array $hit) => Hit::fromRaw($hit, $this->modelResolver));
        }

        return $this->parsedHits;
    }

    public function models(): EloquentCollection
    {
        return new EloquentCollection(
            $this->hits()
                ->map(fn(Hit $hit) => $hit->model())
                ->filter()
                ->values()
                ->all()
        );
    }

    /** @return Collection<int, array> */
    public function documents(): Collection
    {
        return $this->hits()->map(fn(Hit $hit) => $hit->source);
    }

    /** @return Collection<int, array> */
    public function highlights(): Collection
    {
        return $this->hits()
            ->map(fn(Hit $hit) => $hit->highlight)
            ->filter(fn(array $h) => $h !== []);
    }

    /** @return Collection<string, Collection<int, Suggestion>> */
    public function suggestions(): Collection
    {
        if ($this->parsedSuggestions === null) {
            $raw = $this->raw['suggest'] ?? [];
            $result = [];

            foreach ($raw as $name => $entries) {
                $result[$name] = Collection::make($entries)
                    ->map(fn(array $entry) => Suggestion::fromRaw($entry, $this->modelResolver));
            }

            $this->parsedSuggestions = new Collection($result);
        }

        return $this->parsedSuggestions;
    }

    public function aggregations(): array
    {
        return $this->raw['aggregations'] ?? [];
    }

    public function aggregation(string $name): ?array
    {
        return $this->raw['aggregations'][$name] ?? null;
    }

    /** @return Collection<int, array> */
    public function buckets(string $aggregationName): Collection
    {
        $agg = $this->aggregation($aggregationName);
        return new Collection($agg['buckets'] ?? []);
    }

    public function aggregationValue(string $aggregationName, string $key = 'value'): mixed
    {
        $agg = $this->aggregation($aggregationName);
        return $agg[$key] ?? null;
    }

    public function getIterator(): Traversable
    {
        return $this->hits();
    }
}
