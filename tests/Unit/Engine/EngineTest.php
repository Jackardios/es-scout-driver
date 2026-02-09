<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Jackardios\EsScoutDriver\Engine\Engine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    #[Test]
    public function it_is_subclass_of_scout_engine(): void
    {
        $this->assertTrue(is_subclass_of(Engine::class, \Laravel\Scout\Engines\Engine::class));
    }

    #[Test]
    public function map_ids_returns_empty_collection_when_no_hits(): void
    {
        $engine = $this->createEngineWithMockTransport([]);

        $results = [];
        $ids = $engine->mapIds($results);

        $this->assertCount(0, $ids);
    }

    #[Test]
    public function map_ids_returns_collection_of_ids(): void
    {
        $engine = $this->createEngineWithMockTransport([]);

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1'],
                    ['_id' => '2'],
                    ['_id' => '3'],
                ],
            ],
        ];
        $ids = $engine->mapIds($results);

        $this->assertCount(3, $ids);
        $this->assertSame(['1', '2', '3'], $ids->all());
    }

    #[Test]
    public function get_total_count_returns_zero_when_no_hits(): void
    {
        $engine = $this->createEngineWithMockTransport([]);

        $results = [];
        $count = $engine->getTotalCount($results);

        $this->assertSame(0, $count);
    }

    #[Test]
    public function get_total_count_returns_total_value(): void
    {
        $engine = $this->createEngineWithMockTransport([]);

        $results = [
            'hits' => [
                'total' => ['value' => 42],
            ],
        ];
        $count = $engine->getTotalCount($results);

        $this->assertSame(42, $count);
    }

    private function createEngineWithMockTransport(array $responseBody): Engine
    {
        $client = \Elastic\Elasticsearch\ClientBuilder::create()
            ->setHosts(['http://localhost:9200'])
            ->build();

        return new Engine($client);
    }
}
