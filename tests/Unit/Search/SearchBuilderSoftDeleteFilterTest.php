<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Illuminate\Database\Eloquent\Model;
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
        $builder = NonSoftDeleteModel::searchQuery(Query::matchAll());
        $builder->boolQuery()->onlyTrashed();

        $json = json_encode($builder->toArray(), JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"__soft_deleted":{"value":1}', $json);
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
