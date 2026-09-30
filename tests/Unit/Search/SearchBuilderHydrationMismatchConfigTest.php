<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use InvalidArgumentException;
use Jackardios\EsScoutDriver\Search\SearchResult;
use Jackardios\EsScoutDriver\ServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

final class SearchBuilderHydrationMismatchConfigTest extends TestCase
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
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function configuredModes(): iterable
    {
        yield 'null' => [null, SearchResult::HYDRATION_MISMATCH_IGNORE];
        yield 'ignore' => ['ignore', SearchResult::HYDRATION_MISMATCH_IGNORE];
        yield 'padded upper case log' => [' LOG ', SearchResult::HYDRATION_MISMATCH_LOG];
        yield 'mixed case exception' => ['Exception', SearchResult::HYDRATION_MISMATCH_EXCEPTION];
    }

    #[Test]
    #[DataProvider('configuredModes')]
    public function the_configured_mode_is_normalized(mixed $configured, string $expected): void
    {
        config(['elastic.scout.model_hydration_mismatch' => $configured]);

        $result = NonSoftDeleteModel::searchQuery()->execute();

        $this->assertSame($expected, (new ReflectionProperty($result, 'modelHydrationMismatchMode'))->getValue($result));
    }

    #[Test]
    public function an_unknown_mode_is_refused(): void
    {
        config(['elastic.scout.model_hydration_mismatch' => 'loud']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config [elastic.scout.model_hydration_mismatch] must be one of [ignore, log, exception], got [loud].');

        NonSoftDeleteModel::searchQuery()->execute();
    }
}
