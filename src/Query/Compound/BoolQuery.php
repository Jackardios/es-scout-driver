<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Compound;

use Closure;
use Illuminate\Support\Traits\Conditionable;
use Jackardios\EsScoutDriver\Enums\SoftDeleteMode;
use Jackardios\EsScoutDriver\Exceptions\DuplicateKeyedClauseException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasMinimumShouldMatch;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use stdClass;

final class BoolQuery implements QueryInterface
{
    use Conditionable;
    use HasBoost;
    use HasMinimumShouldMatch;

    /** @var array<int|string, QueryInterface|array> */
    private array $must = [];

    /** @var array<int|string, QueryInterface|array> */
    private array $mustNot = [];

    /** @var array<int|string, QueryInterface|array> */
    private array $should = [];

    /** @var array<int|string, QueryInterface|array> */
    private array $filter = [];

    private SoftDeleteMode $softDeleteMode = SoftDeleteMode::ExcludeTrashed;

    // ---- Set methods (replace all clauses) ----

    /** @param QueryInterface|array ...$queries */
    public function setMust(QueryInterface|array ...$queries): self
    {
        $this->must = array_values($queries);
        return $this;
    }

    /** @param QueryInterface|array ...$queries */
    public function setMustNot(QueryInterface|array ...$queries): self
    {
        $this->mustNot = array_values($queries);
        return $this;
    }

    /** @param QueryInterface|array ...$queries */
    public function setShould(QueryInterface|array ...$queries): self
    {
        $this->should = array_values($queries);
        return $this;
    }

    /** @param QueryInterface|array ...$queries */
    public function setFilter(QueryInterface|array ...$queries): self
    {
        $this->filter = array_values($queries);
        return $this;
    }

    // ---- Clear methods ----

    public function clearMust(): self
    {
        $this->must = [];
        return $this;
    }

    public function clearMustNot(): self
    {
        $this->mustNot = [];
        return $this;
    }

    public function clearShould(): self
    {
        $this->should = [];
        return $this;
    }

    public function clearFilter(): self
    {
        $this->filter = [];
        return $this;
    }

    public function clear(): self
    {
        $this->must = [];
        $this->mustNot = [];
        $this->should = [];
        $this->filter = [];
        return $this;
    }

    // ---- Add single clause methods ----

    /**
     * @throws DuplicateKeyedClauseException when key exists and ignoreIfKeyExists is false
     */
    public function addMust(
        QueryInterface|Closure|array $query,
        ?string $key = null,
        bool $ignoreIfKeyExists = true,
    ): self {
        $this->addClause($this->must, 'must', $query, $key, $ignoreIfKeyExists);
        return $this;
    }

    /**
     * @throws DuplicateKeyedClauseException when key exists and ignoreIfKeyExists is false
     */
    public function addMustNot(
        QueryInterface|Closure|array $query,
        ?string $key = null,
        bool $ignoreIfKeyExists = true,
    ): self {
        $this->addClause($this->mustNot, 'must_not', $query, $key, $ignoreIfKeyExists);
        return $this;
    }

    /**
     * @throws DuplicateKeyedClauseException when key exists and ignoreIfKeyExists is false
     */
    public function addShould(
        QueryInterface|Closure|array $query,
        ?string $key = null,
        bool $ignoreIfKeyExists = true,
    ): self {
        $this->addClause($this->should, 'should', $query, $key, $ignoreIfKeyExists);
        return $this;
    }

    /**
     * @throws DuplicateKeyedClauseException when key exists and ignoreIfKeyExists is false
     */
    public function addFilter(
        QueryInterface|Closure|array $query,
        ?string $key = null,
        bool $ignoreIfKeyExists = true,
    ): self {
        $this->addClause($this->filter, 'filter', $query, $key, $ignoreIfKeyExists);
        return $this;
    }

    // ---- Add many clauses methods ----

    /** @param QueryInterface|Closure|array ...$queries */
    public function addMustMany(QueryInterface|Closure|array ...$queries): self
    {
        foreach ($queries as $query) {
            $this->addMust($query);
        }
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function addMustNotMany(QueryInterface|Closure|array ...$queries): self
    {
        foreach ($queries as $query) {
            $this->addMustNot($query);
        }
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function addShouldMany(QueryInterface|Closure|array ...$queries): self
    {
        foreach ($queries as $query) {
            $this->addShould($query);
        }
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function addFilterMany(QueryInterface|Closure|array ...$queries): self
    {
        foreach ($queries as $query) {
            $this->addFilter($query);
        }
        return $this;
    }

    // ---- Remove clause by key ----

    public function removeMust(string $key): self
    {
        unset($this->must[$key]);
        return $this;
    }

    public function removeMustNot(string $key): self
    {
        unset($this->mustNot[$key]);
        return $this;
    }

    public function removeShould(string $key): self
    {
        unset($this->should[$key]);
        return $this;
    }

    public function removeFilter(string $key): self
    {
        unset($this->filter[$key]);
        return $this;
    }

    // ---- Soft delete methods ----

    public function softDelete(SoftDeleteMode $mode): self
    {
        $this->softDeleteMode = $mode;
        return $this;
    }

    public function withTrashed(): self
    {
        $this->softDeleteMode = SoftDeleteMode::WithTrashed;
        return $this;
    }

    public function onlyTrashed(): self
    {
        $this->softDeleteMode = SoftDeleteMode::OnlyTrashed;
        return $this;
    }

    public function excludeTrashed(): self
    {
        $this->softDeleteMode = SoftDeleteMode::ExcludeTrashed;
        return $this;
    }

    public function getSoftDeleteMode(): SoftDeleteMode
    {
        return $this->softDeleteMode;
    }

    // ---- Introspection methods ----

    public function hasClause(string $section, string $key): bool
    {
        $clauses = $this->getSection($section);
        return isset($clauses[$key]);
    }

    public function getClause(string $section, string $key): QueryInterface|array|null
    {
        $clauses = $this->getSection($section);
        return $clauses[$key] ?? null;
    }

    /** @return array<int|string, QueryInterface|array> */
    public function getMustClauses(): array
    {
        return $this->must;
    }

    /** @return array<int|string, QueryInterface|array> */
    public function getMustNotClauses(): array
    {
        return $this->mustNot;
    }

    /** @return array<int|string, QueryInterface|array> */
    public function getShouldClauses(): array
    {
        return $this->should;
    }

    /** @return array<int|string, QueryInterface|array> */
    public function getFilterClauses(): array
    {
        return $this->filter;
    }

    public function hasClauses(): bool
    {
        return $this->must !== []
            || $this->mustNot !== []
            || $this->should !== []
            || $this->filter !== [];
    }

    public function isEmpty(): bool
    {
        return !$this->hasClauses();
    }

    // ---- Serialization ----

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->isEmpty()) {
            return ['match_all' => new stdClass()];
        }

        $bool = [];

        if ($this->must !== []) {
            $bool['must'] = $this->clausesToArray($this->must);
        }

        if ($this->mustNot !== []) {
            $bool['must_not'] = $this->clausesToArray($this->mustNot);
        }

        if ($this->should !== []) {
            $bool['should'] = $this->clausesToArray($this->should);
        }

        if ($this->filter !== []) {
            $bool['filter'] = $this->clausesToArray($this->filter);
        }

        $this->applyMinimumShouldMatch($bool);
        $this->applyBoost($bool);

        return ['bool' => $bool];
    }

    // ---- Cloning ----

    public function __clone(): void
    {
        $this->must = $this->deepCloneClauses($this->must);
        $this->mustNot = $this->deepCloneClauses($this->mustNot);
        $this->should = $this->deepCloneClauses($this->should);
        $this->filter = $this->deepCloneClauses($this->filter);
    }

    // ---- Private helpers ----

    /**
     * @param array<int|string, QueryInterface|array> $clauses
     * @param-out array<int|string, QueryInterface|array> $clauses
     * @param QueryInterface|Closure(BoolQuery):QueryInterface|array<string, mixed> $query
     * @throws DuplicateKeyedClauseException
     */
    private function addClause(
        array &$clauses,
        string $section,
        QueryInterface|Closure|array $query,
        ?string $key,
        bool $ignoreIfKeyExists,
    ): void {
        /** @var QueryInterface|array<string, mixed> $resolved */
        $resolved = $query instanceof Closure ? $query($this) : $query;

        if ($key !== null) {
            if (isset($clauses[$key])) {
                if (!$ignoreIfKeyExists) {
                    throw new DuplicateKeyedClauseException($section, $key);
                }
                return;
            }
            $clauses[$key] = $resolved;
            return;
        }

        $clauses[] = $resolved;
    }

    /** @param array<int|string, QueryInterface|array> $clauses */
    private function clausesToArray(array $clauses): array
    {
        $result = [];

        foreach ($clauses as $clause) {
            $result[] = $clause instanceof QueryInterface ? $clause->toArray() : $clause;
        }

        return $result;
    }

    /**
     * @param array<int|string, QueryInterface|array> $clauses
     * @return array<int|string, QueryInterface|array>
     */
    private function deepCloneClauses(array $clauses): array
    {
        $cloned = [];

        foreach ($clauses as $key => $clause) {
            $cloned[$key] = $clause instanceof QueryInterface ? clone $clause : $clause;
        }

        return $cloned;
    }

    /** @return array<int|string, QueryInterface|array> */
    private function getSection(string $section): array
    {
        return match ($section) {
            'must' => $this->must,
            'must_not' => $this->mustNot,
            'should' => $this->should,
            'filter' => $this->filter,
            default => [],
        };
    }
}
