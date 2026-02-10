<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class Suggestion
{
    private ?Closure $modelResolver;
    private ?EloquentCollection $resolvedModels = null;
    private bool $modelsResolved = false;

    /** @var Collection<int, string>|null */
    private ?Collection $cachedTexts = null;

    /** @var Collection<int, float>|null */
    private ?Collection $cachedScores = null;

    /** @param array<int, array<string, mixed>> $options */
    public function __construct(
        public readonly string $text,
        public readonly int $offset,
        public readonly int $length,
        public readonly array $options,
        ?Closure $modelResolver = null,
    ) {
        $this->modelResolver = $modelResolver;
    }

    public function models(): EloquentCollection
    {
        if (!$this->modelsResolved) {
            $this->resolvedModels = $this->resolveModels();
            $this->modelsResolved = true;
        }

        return $this->resolvedModels ?? new EloquentCollection();
    }

    /** @return Collection<int, string> */
    public function texts(): Collection
    {
        if ($this->cachedTexts === null) {
            $this->cachedTexts = Collection::make($this->options)
                ->pluck('text')
                ->filter(static fn($text) => $text !== null)
                ->values();
        }

        return $this->cachedTexts;
    }

    /** @return Collection<int, float> */
    public function scores(): Collection
    {
        if ($this->cachedScores === null) {
            $this->cachedScores = Collection::make($this->options)
                ->pluck('_score')
                ->filter(fn($score) => $score !== null)
                ->values();
        }

        return $this->cachedScores;
    }

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'offset' => $this->offset,
            'length' => $this->length,
            'options' => $this->options,
        ];
    }

    private function resolveModels(): EloquentCollection
    {
        if ($this->modelResolver === null) {
            return new EloquentCollection();
        }

        $models = [];
        foreach ($this->options as $option) {
            if (isset($option['_index'], $option['_id'])) {
                $model = ($this->modelResolver)($option['_index'], $option['_id']);
                if ($model instanceof Model) {
                    $models[] = $model;
                }
            }
        }

        return new EloquentCollection($models);
    }

    public static function fromRaw(array $raw, ?Closure $modelResolver = null): self
    {
        return new self(
            text: $raw['text'] ?? '',
            offset: $raw['offset'] ?? 0,
            length: $raw['length'] ?? 0,
            options: $raw['options'] ?? [],
            modelResolver: $modelResolver,
        );
    }
}
