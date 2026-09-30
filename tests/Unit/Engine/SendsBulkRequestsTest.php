<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Engine\SendsBulkRequests;
use Jackardios\EsScoutDriver\Exceptions\BulkOperationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class SendsBulkRequestsTest extends TestCase
{
    private const BODY = [['delete' => ['_index' => 'books', '_id' => '1']]];

    private Container $previousContainer;
    private ConfigRepository $config;
    private BulkFailureLoggerSpy $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $this->config = new ConfigRepository();
        $this->logger = new BulkFailureLoggerSpy();
        $container->instance('config', $this->config);
        $container->instance('log', $this->logger);
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    #[Test]
    public function it_sends_the_body_and_the_refresh_flag(): void
    {
        $http = new FakeHttpClient();

        $this->send($http, self::BODY, refresh: true);
        $this->send($http, self::BODY, refresh: false);

        $this->assertSame(self::BODY, $http->bulkLines(0));
        $this->assertSame('refresh=true', $http->requests[0]->getUri()->getQuery());
        $this->assertSame('', $http->requests[1]->getUri()->getQuery());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function successfulResponses(): iterable
    {
        yield 'no errors' => [['errors' => false, 'items' => [['index' => ['_id' => '1', 'result' => 'created']]]]];
        yield 'errors key missing' => [[]];
        yield 'errors without failed items' => [['errors' => true, 'items' => [['index' => ['_id' => '1', 'result' => 'created']]]]];
        yield 'errors with empty items' => [['errors' => true, 'items' => []]];
    }

    #[Test]
    #[DataProvider('successfulResponses')]
    public function it_accepts_a_response_without_failed_items(array $response): void
    {
        $this->send(new FakeHttpClient([[200, $response]]));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_throws_with_every_failed_document_by_default(): void
    {
        try {
            $this->send(new FakeHttpClient([[200, self::failedResponse()]]));
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $this->assertSame([
                ['action' => 'index', 'index' => 'books', 'id' => '1', 'error' => ['type' => 'type1', 'reason' => 'Reason 1']],
                ['action' => 'delete', 'index' => 'books', 'id' => '3', 'error' => ['type' => 'type2', 'reason' => 'Reason 2']],
                ['action' => 'update', 'index' => null, 'id' => null, 'error' => ['type' => 'type3', 'reason' => 'Reason 3']],
            ], $e->getFailedDocuments());
            $this->assertStringContainsString('3 document(s)', $e->getMessage());
        }
    }

    #[Test]
    public function it_logs_failed_documents_in_log_mode(): void
    {
        $this->config->set('elastic.scout.bulk_failure_mode', 'log');

        $this->send(new FakeHttpClient([[200, self::failedResponse()]]));

        $this->assertCount(1, $this->logger->errors);
        $this->assertSame('error', $this->logger->errors[0]['level']);
        $this->assertSame('Elasticsearch bulk operation partially failed', $this->logger->errors[0]['message']);
        $this->assertSame(3, $this->logger->errors[0]['context']['failed_count']);
    }

    #[Test]
    public function it_ignores_failed_documents_in_ignore_mode(): void
    {
        $this->config->set('elastic.scout.bulk_failure_mode', ' Ignore ');

        $this->send(new FakeHttpClient([[200, self::failedResponse()]]));

        $this->assertSame([], $this->logger->errors);
    }

    #[Test]
    public function it_refuses_an_unknown_failure_mode_before_sending(): void
    {
        $this->config->set('elastic.scout.bulk_failure_mode', 'skip');
        $http = new FakeHttpClient();

        try {
            $this->send($http);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(
                'Config [elastic.scout.bulk_failure_mode] must be one of [exception, log, ignore], got [skip].',
                $e->getMessage(),
            );
        }

        $this->assertSame([], $http->requests);
    }

    /** @param list<array<string, mixed>> $body */
    private function send(FakeHttpClient $http, array $body = self::BODY, bool $refresh = false): void
    {
        $sender = new class {
            use SendsBulkRequests {
                sendBulk as public;
            }
        };

        $sender->sendBulk($http->client(), $body, $refresh);
    }

    /** @return array<string, mixed> */
    private static function failedResponse(): array
    {
        return [
            'errors' => true,
            'items' => [
                ['index' => ['_id' => '1', '_index' => 'books', 'error' => ['type' => 'type1', 'reason' => 'Reason 1']]],
                ['index' => ['_id' => '2', '_index' => 'books', 'result' => 'created']],
                ['delete' => ['_id' => '3', '_index' => 'books', 'error' => ['type' => 'type2', 'reason' => 'Reason 2']]],
                ['update' => ['error' => ['type' => 'type3', 'reason' => 'Reason 3']]],
            ],
        ];
    }
}

final class BulkFailureLoggerSpy extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $errors = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->errors[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
