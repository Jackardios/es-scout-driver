<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Support;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Support\ConfigOption;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigOptionTest extends TestCase
{
    private Container $previousContainer;
    private ConfigRepository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $this->config = new ConfigRepository(['options' => ['null' => null, 'mixed' => ' LOG ', 'bool' => true]]);
        $container->instance('config', $this->config);
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    /** @return iterable<string, array{string, string}> */
    public static function readableOptions(): iterable
    {
        yield 'missing key' => ['options.missing', 'ignore'];
        yield 'null' => ['options.null', 'ignore'];
        yield 'mixed case with spaces' => ['options.mixed', 'log'];
    }

    #[Test]
    #[DataProvider('readableOptions')]
    public function one_of_normalizes_the_value_or_falls_back_to_the_default(string $key, string $expected): void
    {
        $this->assertSame($expected, ConfigOption::oneOf($key, ['ignore', 'log'], 'ignore'));
    }

    #[Test]
    public function one_of_refuses_a_value_outside_the_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config [options.bool] must be one of [ignore, log], got [1].');

        ConfigOption::oneOf('options.bool', ['ignore', 'log'], 'ignore');
    }
}
