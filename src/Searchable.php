<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver;

use Closure;
use Jackardios\EsScoutDriver\Engine\EngineInterface;
use Jackardios\EsScoutDriver\Exceptions\SearchException;
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

    /** @return EngineInterface */
    public function searchableUsing()
    {
        $engine = $this->baseSearchableUsing();

        if (!$engine instanceof EngineInterface) {
            throw new SearchException(sprintf(
                'Search engine %s does not support es-scout-driver features. Configure SCOUT_DRIVER=elastic or null.',
                get_debug_type($engine),
            ));
        }

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
