<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Records the requests of an Elasticsearch client and answers them with queued responses.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param list<array{0: int, 1: array<string, mixed>}> $responses status code and JSON body, answered in order
     */
    public function __construct(private array $responses = []) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        [$status, $body] = array_shift($this->responses) ?? [200, ['errors' => false, 'items' => []]];

        return new Response(
            $status,
            ['Content-Type' => 'application/json', 'X-Elastic-Product' => 'Elasticsearch'],
            (string) json_encode($body),
        );
    }

    public function client(): Client
    {
        return ClientBuilder::create()->setHttpClient($this)->build();
    }

    /** @return list<array<string, mixed>> the NDJSON lines of a bulk request */
    public function bulkLines(int $request = 0): array
    {
        $lines = array_filter(explode("\n", (string) $this->requests[$request]->getBody()));

        return array_values(array_map(static fn(string $line): array => json_decode($line, true), $lines));
    }
}
