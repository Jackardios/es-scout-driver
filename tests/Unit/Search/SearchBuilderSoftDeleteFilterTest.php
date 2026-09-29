<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Illuminate\Database\Eloquent\Model;
use Jackardios\EsScoutDriver\Enums\SoftDeleteMode;
use Jackardios\EsScoutDriver\Searchable;
use Jackardios\EsScoutDriver\ServiceProvider;
use Jackardios\EsScoutDriver\Support\Query;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SearchBuilderSoftDeleteFilterTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ScoutServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('scout.driver', 'null');
        $app['config']->set('scout.soft_delete', true);
    }

    #[Test]
    public function exclude_trashed_mode_includes_documents_without_soft_delete_flag(): void
    {
        $params = NonSoftDeleteModel::searchQuery(Query::matchAll())->toArray();
        $json = json_encode($params, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"minimum_should_match":1', $json);
        $this->assertStringContainsString('"value":0', $json);
        $this->assertStringContainsString('"must_not":[{"exists":{"field":"__soft_deleted"}}]', $json);
    }

    #[Test]
    public function only_trashed_mode_uses_explicit_soft_delete_flag_filter(): void
    {
        $builder = NonSoftDeleteModel::searchQuery(Query::matchAll())->onlyTrashed();

        $json = json_encode($builder->toArray(), JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"__soft_deleted":{"value":1}', $json);
    }

    #[Test]
    public function with_trashed_mode_adds_no_soft_delete_filter(): void
    {
        $builder = NonSoftDeleteModel::searchQuery(Query::matchAll())->withTrashed();

        $this->assertSame(SoftDeleteMode::WithTrashed, $builder->getSoftDeleteMode());
        $this->assertStringNotContainsString('__soft_deleted', json_encode($builder->toArray(), JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function soft_delete_mode_survives_clearing_the_bool_query(): void
    {
        $builder = NonSoftDeleteModel::searchQuery()->onlyTrashed();
        $builder->filter(Query::term('status', 'active'));
        $builder->clearBoolQuery();

        $this->assertSame(SoftDeleteMode::OnlyTrashed, $builder->getSoftDeleteMode());
        $this->assertStringContainsString(
            '"__soft_deleted":{"value":1}',
            json_encode($builder->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function a_bool_of_should_clauses_alone_still_requires_one_of_them(): void
    {
        $query = NonSoftDeleteModel::searchQuery()
            ->should(Query::term('tag', 'a'))
            ->should(Query::term('tag', 'b'))
            ->toArray()['body']['query'];

        $this->assertSame(
            [['bool' => ['should' => [
                ['term' => ['tag' => ['value' => 'a']]],
                ['term' => ['tag' => ['value' => 'b']]],
            ]]]],
            $query['bool']['must'],
        );
        $this->assertCount(1, $query['bool']['filter']);
        $this->assertStringContainsString('__soft_deleted', json_encode($query['bool']['filter'], JSON_THROW_ON_ERROR));
        $this->assertArrayNotHasKey('should', $query['bool']);
    }

    #[Test]
    public function a_bool_with_a_required_clause_takes_the_soft_delete_filter_beside_it(): void
    {
        $query = NonSoftDeleteModel::searchQuery(Query::match('title', 'x'))
            ->should(Query::term('tag', 'a'))
            ->filter(Query::term('status', 'active'))
            ->toArray()['body']['query'];

        $this->assertSame([['match' => ['title' => ['query' => 'x']]]], $query['bool']['must']);
        $this->assertSame([['term' => ['tag' => ['value' => 'a']]]], $query['bool']['should']);
        $this->assertCount(2, $query['bool']['filter']);
        $this->assertSame(['term' => ['status' => ['value' => 'active']]], $query['bool']['filter'][0]);
    }

    #[Test]
    public function building_leaves_the_bool_query_unchanged(): void
    {
        $builder = NonSoftDeleteModel::searchQuery()->should(Query::term('tag', 'a'));
        $builder->toArray();

        $this->assertSame(
            ['bool' => ['should' => [['term' => ['tag' => ['value' => 'a']]]]]],
            $builder->boolQuery()->toArray(),
        );
    }

    #[Test]
    public function clear_all_resets_the_soft_delete_mode(): void
    {
        $builder = NonSoftDeleteModel::searchQuery()->onlyTrashed()->clearAll();

        $this->assertSame(SoftDeleteMode::ExcludeTrashed, $builder->getSoftDeleteMode());
    }

    #[Test]
    public function soft_delete_mode_setters_return_the_builder(): void
    {
        $builder = NonSoftDeleteModel::searchQuery();

        $this->assertSame($builder, $builder->withTrashed());
        $this->assertSame($builder, $builder->onlyTrashed());
        $this->assertSame($builder, $builder->excludeTrashed());
        $this->assertSame($builder, $builder->softDelete(SoftDeleteMode::WithTrashed));
        $this->assertSame(SoftDeleteMode::WithTrashed, $builder->getSoftDeleteMode());
    }
}

class NonSoftDeleteModel extends Model
{
    use Searchable;

    public function searchableAs(): string
    {
        return 'non_soft_delete_models';
    }

    public function toSearchableArray(): array
    {
        return ['id' => 1];
    }
}
