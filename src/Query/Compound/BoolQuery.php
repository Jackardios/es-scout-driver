<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Compound;

use Closure;
use InvalidArgumentException;
use Illuminate\Support\Traits\Conditionable;
use Jackardios\EsScoutDriver\Exceptions\DuplicateKeyedClauseException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\Concerns\HasMinimumShouldMatch;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\SubQuery;
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

    /** @var array<string, array<int|string, true>> the keys of the keyed clauses, by section */
    private array $keys = ['must' => [], 'must_not' => [], 'should' => [], 'filter' => []];

    public function setMust(QueryInterface|array ...$queries): self
    {
        $this->must = array_values($queries);
        $this->keys['must'] = [];
        return $this;
    }

    public function setMustNot(QueryInterface|array ...$queries): self
    {
        $this->mustNot = array_values($queries);
        $this->keys['must_not'] = [];
        return $this;
    }

    public function setShould(QueryInterface|array ...$queries): self
    {
        $this->should = array_values($queries);
        $this->keys['should'] = [];
        return $this;
    }

    public function setFilter(QueryInterface|array ...$queries): self
    {
        $this->filter = array_values($queries);
        $this->keys['filter'] = [];
        return $this;
    }

    public function clearMust(): self
    {
        $this->must = [];
        $this->keys['must'] = [];
        return $this;
    }

    public function clearMustNot(): self
    {
        $this->mustNot = [];
        $this->keys['must_not'] = [];
        return $this;
    }

    public function clearShould(): self
    {
        $this->should = [];
        $this->keys['should'] = [];
        return $this;
    }

    public function clearFilter(): self
    {
        $this->filter = [];
        $this->keys['filter'] = [];
        return $this;
    }

    public function clear(): self
    {
        $this->must = [];
        $this->mustNot = [];
        $this->should = [];
        $this->filter = [];
        $this->keys = ['must' => [], 'must_not' => [], 'should' => [], 'filter' => []];
        return $this;
    }

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

    public function must(QueryInterface|Closure|array ...$queries): self
    {
        foreach ($queries as $query) {
            $this->addMust($query);
        }
        return $this;
    }

    public function mustNot(QueryInterface|Closure|array ...$queries): self
    {
        foreach ($queries as $query) {
            $this->addMustNot($query);
        }
        return $this;
    }

    public function should(QueryInterface|Closure|array ...$queries): self
    {
        foreach ($queries as $query) {
            $this->addShould($query);
        }
        return $this;
    }

    public function filter(QueryInterface|Closure|array ...$queries): self
    {
        foreach ($queries as $query) {
            $this->addFilter($query);
        }
        return $this;
    }

    public function removeMust(string $key): self
    {
        $this->removeClause($this->must, 'must', $key);
        return $this;
    }

    public function removeMustNot(string $key): self
    {
        $this->removeClause($this->mustNot, 'must_not', $key);
        return $this;
    }

    public function removeShould(string $key): self
    {
        $this->removeClause($this->should, 'should', $key);
        return $this;
    }

    public function removeFilter(string $key): self
    {
        $this->removeClause($this->filter, 'filter', $key);
        return $this;
    }

    /**
     * @param string $section must, must_not, should or filter
     *
     * @throws InvalidArgumentException for another section name
     */
    public function hasClause(string $section, string $key): bool
    {
        $this->getSection($section);
        return isset($this->keys[$section][$key]);
    }

    /**
     * @param string $section must, must_not, should or filter
     *
     * @throws InvalidArgumentException for another section name
     */
    public function getClause(string $section, string $key): QueryInterface|array|null
    {
        $clauses = $this->getSection($section);
        return isset($this->keys[$section][$key]) ? $clauses[$key] : null;
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->isEmpty()) {
            $matchAll = [];
            $this->applyBoost($matchAll);

            return ['match_all' => $matchAll === [] ? new stdClass() : $matchAll];
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

    public function __clone(): void
    {
        $this->must = $this->deepCloneClauses($this->must);
        $this->mustNot = $this->deepCloneClauses($this->mustNot);
        $this->should = $this->deepCloneClauses($this->should);
        $this->filter = $this->deepCloneClauses($this->filter);
    }

    /**
     * @param array<int|string, QueryInterface|array> $clauses
     * @param-out array<int|string, QueryInterface|array> $clauses
     * @param QueryInterface|Closure():(QueryInterface|array<string, mixed>)|array<string, mixed> $query
     * @throws DuplicateKeyedClauseException
     */
    private function addClause(
        array &$clauses,
        string $section,
        QueryInterface|Closure|array $query,
        ?string $key,
        bool $ignoreIfKeyExists,
    ): void {
        $resolved = SubQuery::resolve($query);

        if ($key === null) {
            $clauses[] = $resolved;
            return;
        }

        if (isset($this->keys[$section][$key])) {
            if (!$ignoreIfKeyExists) {
                throw new DuplicateKeyedClauseException($section, $key);
            }
            return;
        }

        if (array_key_exists($key, $clauses)) {
            $clauses[] = $clauses[$key];
        }

        $clauses[$key] = $resolved;
        $this->keys[$section][$key] = true;
    }

    /**
     * @param array<int|string, QueryInterface|array> $clauses
     * @param-out array<int|string, QueryInterface|array> $clauses
     */
    private function removeClause(array &$clauses, string $section, string $key): void
    {
        if (isset($this->keys[$section][$key])) {
            unset($clauses[$key], $this->keys[$section][$key]);
        }
    }

    /** @param array<int|string, QueryInterface|array> $clauses */
    private function clausesToArray(array $clauses): array
    {
        return array_map(SubQuery::toArray(...), array_values($clauses));
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
            default => throw new InvalidArgumentException(sprintf(
                'Unknown bool query section `%s`; expected must, must_not, should or filter.',
                $section,
            )),
        };
    }
}
