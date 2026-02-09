<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver;

use Closure;
use Jackardios\EsScoutDriver\Engine\Engine;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Laravel\Scout\Searchable as BaseSearchable;

trait Searchable
{
    use BaseSearchable {
        searchableUsing as baseSearchableUsing;
    }

    /** @param QueryInterface|Closure|array|null $query */
    public static function searchQuery($query = null): SearchBuilder
    {
        $builder = new SearchBuilder(new static(), $query);

        return $builder;
    }

    /** @return string|int|null */
    public function searchableRouting()
    {
        return null;
    }

    /** @return array|string|null */
    public function searchableWith()
    {
        return null;
    }

    public function searchableConnection(): ?string
    {
        return null;
    }

    /** @return Engine */
    public function searchableUsing()
    {
        /** @var Engine $engine */
        $engine = $this->baseSearchableUsing();
        $connection = $this->searchableConnection();

        return $connection !== null ? $engine->connection($connection) : $engine;
    }

    public static function openPointInTime(?string $keepAlive = null): string
    {
        $self = new static();
        $engine = $self->searchableUsing();
        $indexName = $self->searchableAs();

        return $engine->openPointInTime($indexName, $keepAlive);
    }

    public static function closePointInTime(string $pointInTimeId): void
    {
        $self = new static();
        $engine = $self->searchableUsing();

        $engine->closePointInTime($pointInTimeId);
    }
}
