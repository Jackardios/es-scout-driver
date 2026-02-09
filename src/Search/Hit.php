<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class Hit
{
    private ?Closure $modelResolver;
    private ?Model $resolvedModel = null;
    private bool $modelResolved = false;

    /** @var Collection<string, Collection<int, Hit>>|null */
    private ?Collection $cachedInnerHits = null;

    public function __construct(
        public readonly string $indexName,
        public readonly string $documentId,
        public readonly ?float $score,
        public readonly array $source,
        public readonly array $highlight,
        public readonly array $sort,
        public readonly array $explanation,
        public readonly array $raw,
        ?Closure $modelResolver = null,
    ) {
        $this->modelResolver = $modelResolver;
    }

    public function model(): ?Model
    {
        if (!$this->modelResolved) {
            $this->resolvedModel = $this->modelResolver
                ? ($this->modelResolver)($this->indexName, $this->documentId)
                : null;
            $this->modelResolved = true;
        }

        return $this->resolvedModel;
    }

    /** @return Collection<string, Collection<int, Hit>> */
    public function innerHits(): Collection
    {
        if ($this->cachedInnerHits === null) {
            $this->cachedInnerHits = $this->parseInnerHits();
        }

        return $this->cachedInnerHits;
    }

    public function toArray(): array
    {
        return [
            'index_name' => $this->indexName,
            'document_id' => $this->documentId,
            'score' => $this->score,
            'source' => $this->source,
            'highlight' => $this->highlight,
            'sort' => $this->sort,
            'explanation' => $this->explanation,
        ];
    }

    public static function fromRaw(array $rawHit, ?Closure $modelResolver = null): self
    {
        return new self(
            indexName: $rawHit['_index'] ?? '',
            documentId: $rawHit['_id'] ?? '',
            score: $rawHit['_score'] ?? null,
            source: $rawHit['_source'] ?? [],
            highlight: $rawHit['highlight'] ?? [],
            sort: $rawHit['sort'] ?? [],
            explanation: $rawHit['_explanation'] ?? [],
            raw: $rawHit,
            modelResolver: $modelResolver,
        );
    }

    /** @return Collection<string, Collection<int, Hit>> */
    private function parseInnerHits(): Collection
    {
        if (!isset($this->raw['inner_hits'])) {
            return new Collection();
        }

        $result = [];
        foreach ($this->raw['inner_hits'] as $name => $innerHitsGroup) {
            $hits = [];
            foreach ($innerHitsGroup['hits']['hits'] ?? [] as $rawHit) {
                $hits[] = self::fromRaw($rawHit, $this->modelResolver);
            }
            $result[$name] = new Collection($hits);
        }

        return new Collection($result);
    }
}
