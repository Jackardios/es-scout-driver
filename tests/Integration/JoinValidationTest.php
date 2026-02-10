<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Illuminate\Database\Eloquent\Model;
use Jackardios\EsScoutDriver\Exceptions\AmbiguousJoinedIndexException;
use Jackardios\EsScoutDriver\Exceptions\IncompatibleSearchConnectionException;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Searchable;
use PHPUnit\Framework\Attributes\Test;

final class JoinValidationTest extends TestCase
{
    #[Test]
    public function join_throws_for_models_with_different_searchable_connections(): void
    {
        $baseModelClass = get_class(new class extends Model {
            use Searchable;

            public function searchableAs(): string
            {
                return 'books';
            }
        });

        $joinedModelClass = get_class(new class extends Model {
            use Searchable;

            public function searchableAs(): string
            {
                return 'authors';
            }

            public function searchableConnection(): ?string
            {
                return 'secondary';
            }
        });

        $this->expectException(IncompatibleSearchConnectionException::class);

        (new SearchBuilder(new $baseModelClass()))->join($joinedModelClass);
    }

    #[Test]
    public function join_throws_when_two_models_map_to_the_same_index(): void
    {
        $baseModelClass = get_class(new class extends Model {
            use Searchable;

            public function searchableAs(): string
            {
                return 'books';
            }
        });

        $joinedModelClass = get_class(new class extends Model {
            use Searchable;

            public function searchableAs(): string
            {
                return 'books';
            }
        });

        $this->expectException(AmbiguousJoinedIndexException::class);

        (new SearchBuilder(new $baseModelClass()))->join($joinedModelClass);
    }
}
