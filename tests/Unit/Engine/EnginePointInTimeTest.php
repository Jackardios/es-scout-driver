<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Response;
use Jackardios\EsScoutDriver\Engine\Engine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class EnginePointInTimeTest extends TestCase
{
    #[Test]
    public function open_point_in_time_passes_routing_and_preference(): void
    {
        $http = $this->createHttpClient();
        $engine = new Engine(ClientBuilder::create()->setHttpClient($http)->build());

        $this->assertSame('pit-1', $engine->openPointInTime('books', '1m', 'r1,r2', '_local'));

        parse_str($http->requests[0]->getUri()->getQuery(), $query);
        $this->assertSame('/books/_pit', $http->requests[0]->getUri()->getPath());
        $this->assertEquals(['keep_alive' => '1m', 'routing' => 'r1,r2', 'preference' => '_local'], $query);
    }

    #[Test]
    public function open_point_in_time_omits_routing_and_preference_by_default(): void
    {
        $http = $this->createHttpClient();
        $engine = new Engine(ClientBuilder::create()->setHttpClient($http)->build());

        $engine->openPointInTime('books');

        parse_str($http->requests[0]->getUri()->getQuery(), $query);
        $this->assertSame(['keep_alive' => '5m'], $query);
    }

    /** @return ClientInterface&object{requests: list<RequestInterface>} */
    private function createHttpClient(): ClientInterface
    {
        return new class implements ClientInterface {
            /** @var list<RequestInterface> */
            public array $requests = [];

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;

                return new Response(
                    200,
                    ['Content-Type' => 'application/json', 'X-Elastic-Product' => 'Elasticsearch'],
                    '{"id":"pit-1"}',
                );
            }
        };
    }
}
