<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Jackardios\EsScoutDriver\Engine\HandlesBulkResponse;
use Jackardios\EsScoutDriver\Exceptions\BulkOperationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class HandlesBulkResponseTest extends TestCase
{
    #[Test]
    public function it_does_not_throw_when_errors_is_false(): void
    {
        $handler = $this->createHandler();

        $this->invokeHandleBulkResponse($handler, [
            'errors' => false,
            'items' => [
                ['index' => ['_id' => '1', '_index' => 'test', 'result' => 'created']],
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_does_not_throw_when_errors_key_is_missing(): void
    {
        $handler = $this->createHandler();

        $this->invokeHandleBulkResponse($handler, []);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_does_not_throw_when_errors_is_true_but_no_items_have_errors(): void
    {
        $handler = $this->createHandler();

        $this->invokeHandleBulkResponse($handler, [
            'errors' => true,
            'items' => [
                ['index' => ['_id' => '1', '_index' => 'test', 'result' => 'created']],
                ['index' => ['_id' => '2', '_index' => 'test', 'result' => 'updated']],
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function it_throws_bulk_operation_exception_on_partial_errors(): void
    {
        $handler = $this->createHandler();

        $this->expectException(BulkOperationException::class);

        $this->invokeHandleBulkResponse($handler, [
            'errors' => true,
            'items' => [
                ['index' => ['_id' => '1', '_index' => 'test', 'result' => 'created']],
                [
                    'index' => [
                        '_id' => '2',
                        '_index' => 'test',
                        'error' => ['type' => 'mapper_parsing_exception', 'reason' => 'Invalid field value'],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function it_throws_bulk_operation_exception_on_full_errors(): void
    {
        $handler = $this->createHandler();

        $this->expectException(BulkOperationException::class);

        $this->invokeHandleBulkResponse($handler, [
            'errors' => true,
            'items' => [
                [
                    'index' => [
                        '_id' => '1',
                        '_index' => 'test',
                        'error' => ['type' => 'error1', 'reason' => 'Reason 1'],
                    ],
                ],
                [
                    'index' => [
                        '_id' => '2',
                        '_index' => 'test',
                        'error' => ['type' => 'error2', 'reason' => 'Reason 2'],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function it_collects_all_failed_documents(): void
    {
        $handler = $this->createHandler();

        $response = [
            'errors' => true,
            'items' => [
                [
                    'index' => [
                        '_id' => '1',
                        '_index' => 'books',
                        'error' => ['type' => 'type1', 'reason' => 'Reason 1'],
                    ],
                ],
                ['index' => ['_id' => '2', '_index' => 'books', 'result' => 'created']],
                [
                    'delete' => [
                        '_id' => '3',
                        '_index' => 'books',
                        'error' => ['type' => 'type2', 'reason' => 'Reason 2'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($handler, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $failedDocs = $e->getFailedDocuments();

            $this->assertCount(2, $failedDocs);

            $this->assertSame('index', $failedDocs[0]['action']);
            $this->assertSame('1', $failedDocs[0]['id']);
            $this->assertSame('books', $failedDocs[0]['index']);
            $this->assertSame(['type' => 'type1', 'reason' => 'Reason 1'], $failedDocs[0]['error']);

            $this->assertSame('delete', $failedDocs[1]['action']);
            $this->assertSame('3', $failedDocs[1]['id']);
            $this->assertSame('books', $failedDocs[1]['index']);
            $this->assertSame(['type' => 'type2', 'reason' => 'Reason 2'], $failedDocs[1]['error']);
        }
    }

    #[Test]
    public function it_handles_different_bulk_action_types(): void
    {
        $handler = $this->createHandler();

        $response = [
            'errors' => true,
            'items' => [
                [
                    'create' => [
                        '_id' => '1',
                        '_index' => 'test',
                        'error' => ['type' => 'create_error', 'reason' => 'Already exists'],
                    ],
                ],
                [
                    'update' => [
                        '_id' => '2',
                        '_index' => 'test',
                        'error' => ['type' => 'update_error', 'reason' => 'Version conflict'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($handler, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $failedDocs = $e->getFailedDocuments();

            $this->assertCount(2, $failedDocs);
            $this->assertSame('create', $failedDocs[0]['action']);
            $this->assertSame('update', $failedDocs[1]['action']);
        }
    }

    #[Test]
    public function it_handles_missing_index_in_error_response(): void
    {
        $handler = $this->createHandler();

        $response = [
            'errors' => true,
            'items' => [
                [
                    'index' => [
                        '_id' => '1',
                        'error' => ['type' => 'error', 'reason' => 'Failed'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($handler, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $failedDocs = $e->getFailedDocuments();

            $this->assertCount(1, $failedDocs);
            $this->assertNull($failedDocs[0]['index']);
        }
    }

    #[Test]
    public function it_handles_missing_id_in_error_response(): void
    {
        $handler = $this->createHandler();

        $response = [
            'errors' => true,
            'items' => [
                [
                    'index' => [
                        '_index' => 'test',
                        'error' => ['type' => 'error', 'reason' => 'Failed'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($handler, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $failedDocs = $e->getFailedDocuments();

            $this->assertCount(1, $failedDocs);
            $this->assertNull($failedDocs[0]['id']);
        }
    }

    #[Test]
    public function it_handles_empty_items_array(): void
    {
        $handler = $this->createHandler();

        $this->invokeHandleBulkResponse($handler, [
            'errors' => true,
            'items' => [],
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function exception_message_contains_document_count(): void
    {
        $handler = $this->createHandler();

        $response = [
            'errors' => true,
            'items' => [
                [
                    'index' => [
                        '_id' => '1',
                        '_index' => 'test',
                        'error' => ['type' => 'error', 'reason' => 'Failed'],
                    ],
                ],
                [
                    'index' => [
                        '_id' => '2',
                        '_index' => 'test',
                        'error' => ['type' => 'error', 'reason' => 'Failed'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($handler, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $this->assertStringContainsString('2 document(s)', $e->getMessage());
        }
    }

    private function createHandler(): object
    {
        return new class {
            use HandlesBulkResponse {
                handleBulkResponse as public;
            }
        };
    }

    private function invokeHandleBulkResponse(object $handler, array $response): void
    {
        $method = new ReflectionMethod(get_class($handler), 'handleBulkResponse');
        $method->invoke($handler, $response);
    }
}
