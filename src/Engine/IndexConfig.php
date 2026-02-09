<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
final class IndexConfig
{
    /**
     * @param class-string<TModel> $modelClass
     * @param array<int, string> $relations
     * @param array<int, Closure> $queryCallbacks
     * @param array<int, Closure> $collectionCallbacks
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly array $relations = [],
        public readonly array $queryCallbacks = [],
        public readonly array $collectionCallbacks = [],
        public readonly bool $withTrashed = false,
    ) {}
}
