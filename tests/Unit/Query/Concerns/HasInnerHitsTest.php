<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Concerns;

use Jackardios\EsScoutDriver\Query\Concerns\HasInnerHits;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class HasInnerHitsTest extends TestCase
{
    private function createTestClass(): object
    {
        return new class {
            use HasInnerHits;

            public function buildParams(): array
            {
                $params = [];
                $this->applyInnerHits($params);
                return $params;
            }
        };
    }

    #[Test]
    public function it_applies_no_params_by_default(): void
    {
        $obj = $this->createTestClass();

        $this->assertSame([], $obj->buildParams());
    }

    #[Test]
    public function it_applies_inner_hits_with_options(): void
    {
        $obj = $this->createTestClass();
        $obj->innerHits(['name' => 'my_hits', 'size' => 5]);

        $this->assertSame([
            'inner_hits' => ['name' => 'my_hits', 'size' => 5],
        ], $obj->buildParams());
    }

    #[Test]
    public function it_converts_empty_array_to_stdclass(): void
    {
        $obj = $this->createTestClass();
        $obj->innerHits([]);

        $params = $obj->buildParams();

        $this->assertArrayHasKey('inner_hits', $params);
        $this->assertInstanceOf(stdClass::class, $params['inner_hits']);
    }

    #[Test]
    public function it_converts_no_argument_to_stdclass(): void
    {
        $obj = $this->createTestClass();
        $obj->innerHits();

        $params = $obj->buildParams();

        $this->assertArrayHasKey('inner_hits', $params);
        $this->assertInstanceOf(stdClass::class, $params['inner_hits']);
    }

    #[Test]
    public function it_applies_inner_hits_with_nested_options(): void
    {
        $obj = $this->createTestClass();
        $obj->innerHits([
            'name' => 'nested_hits',
            'size' => 10,
            '_source' => ['field1', 'field2'],
            'highlight' => ['fields' => ['content' => new stdClass()]],
        ]);

        $result = $obj->buildParams();

        $this->assertSame('nested_hits', $result['inner_hits']['name']);
        $this->assertSame(10, $result['inner_hits']['size']);
        $this->assertSame(['field1', 'field2'], $result['inner_hits']['_source']);
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $obj = $this->createTestClass();

        $this->assertSame($obj, $obj->innerHits(['size' => 3]));
    }
}
