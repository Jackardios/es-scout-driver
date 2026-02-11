<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Jackardios\EsScoutDriver\Exceptions\ModelHydrationMismatchException;
use Throwable;
use Traversable;

/**
 * @implements IteratorAggregate<int, Hit>
 */
final class SearchResult implements IteratorAggregate
{
    public const HYDRATION_MISMATCH_IGNORE = 'ignore';
    public const HYDRATION_MISMATCH_LOG = 'log';
    public const HYDRATION_MISMATCH_EXCEPTION = 'exception';

    public readonly int $total;
    public readonly ?float $maxScore;

    private ?Closure $modelResolver;
    private string $modelHydrationMismatchMode;

    /** @var Collection<int, Hit>|null */
    private ?Collection $parsedHits = null;

    /** @var Collection<string, Collection<int, Suggestion>>|null */
    private ?Collection $parsedSuggestions = null;

    private ?EloquentCollection $parsedModels = null;

    public function __construct(
        public readonly array $raw,
        ?Closure $modelResolver = null,
        string $modelHydrationMismatchMode = self::HYDRATION_MISMATCH_IGNORE,
    ) {
        $this->modelResolver = $modelResolver;
        $this->modelHydrationMismatchMode = $this->normalizeHydrationMismatchMode($modelHydrationMismatchMode);
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
        if ($this->parsedModels === null) {
            $hits = $this->hits();
            $resolvedModels = $hits
                ->map(fn(Hit $hit): ?Model => $hit->model())
                ->values();

            $models = $resolvedModels
                ->filter(static fn(?Model $model): bool => $model instanceof Model)
                ->values();

            $missingModels = $resolvedModels->count() - $models->count();

            if ($missingModels > 0) {
                $missingDocuments = [];

                foreach ($resolvedModels as $index => $model) {
                    if ($model instanceof Model) {
                        continue;
                    }

                    /** @var Hit $hit */
                    $hit = $hits[$index];
                    $missingDocuments[] = [
                        'index' => $hit->indexName,
                        'id' => $hit->documentId,
                    ];
                }

                $this->handleHydrationMismatch($hits->count(), $models->count(), $missingModels, $missingDocuments);
            }

            $this->parsedModels = new EloquentCollection($models->all());
        }

        return $this->parsedModels;
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

    /**
     * @param array<int, array{index: string, id: string}> $missingDocuments
     */
    private function handleHydrationMismatch(
        int $totalHits,
        int $resolvedModels,
        int $missingModels,
        array $missingDocuments,
    ): void {
        if ($this->modelHydrationMismatchMode === self::HYDRATION_MISMATCH_IGNORE) {
            return;
        }

        $exception = new ModelHydrationMismatchException(
            totalHits: $totalHits,
            resolvedModels: $resolvedModels,
            missingModels: $missingModels,
            missingDocuments: $missingDocuments,
        );

        if ($this->modelHydrationMismatchMode === self::HYDRATION_MISMATCH_EXCEPTION) {
            throw $exception;
        }

        if (!function_exists('logger')) {
            return;
        }

        try {
            logger()->warning($exception->getMessage(), [
                'total_hits' => $totalHits,
                'resolved_models' => $resolvedModels,
                'missing_models' => $missingModels,
                'missing_documents' => $missingDocuments,
            ]);
        } catch (Throwable) {
            // Logging is best-effort and should never break result consumption.
        }
    }

    private function normalizeHydrationMismatchMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));

        return match ($normalized) {
            self::HYDRATION_MISMATCH_LOG,
            self::HYDRATION_MISMATCH_EXCEPTION => $normalized,
            default => self::HYDRATION_MISMATCH_IGNORE,
        };
    }
}
