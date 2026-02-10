<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Exceptions;

use Jackardios\EsScoutDriver\Exceptions\BulkOperationException;
use Jackardios\EsScoutDriver\Exceptions\DuplicateKeyedClauseException;
use Jackardios\EsScoutDriver\Exceptions\AmbiguousJoinedIndexException;
use Jackardios\EsScoutDriver\Exceptions\IncompatibleSearchConnectionException;
use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Exceptions\InvalidSearchResultException;
use Jackardios\EsScoutDriver\Exceptions\ModelNotJoinedException;
use Jackardios\EsScoutDriver\Exceptions\NotSearchableModelException;
use Jackardios\EsScoutDriver\Exceptions\SearchException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionsTest extends TestCase
{
    #[Test]
    public function search_exception_extends_runtime_exception(): void
    {
        $e = new SearchException('test');
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function model_not_joined_exception_extends_invalid_argument(): void
    {
        $e = new ModelNotJoinedException('App\\Models\\Book');
        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        $this->assertStringContainsString('App\\Models\\Book', $e->getMessage());
    }

    #[Test]
    public function not_searchable_model_exception_extends_invalid_argument(): void
    {
        $e = new NotSearchableModelException('App\\Models\\Post');
        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        $this->assertStringContainsString('App\\Models\\Post', $e->getMessage());
    }

    #[Test]
    public function bulk_operation_exception_extends_runtime_exception(): void
    {
        $e = new BulkOperationException([]);
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    #[Test]
    public function bulk_operation_exception_with_default_message(): void
    {
        $failedDocs = [
            ['id' => '1', 'error' => 'some error'],
            ['id' => '2', 'error' => 'another error'],
        ];
        $e = new BulkOperationException($failedDocs);

        $this->assertStringContainsString('2 document(s)', $e->getMessage());
    }

    #[Test]
    public function bulk_operation_exception_with_custom_message(): void
    {
        $failedDocs = [['id' => '1', 'error' => 'some error']];
        $e = new BulkOperationException($failedDocs, 'Custom error message');

        $this->assertSame('Custom error message', $e->getMessage());
    }

    #[Test]
    public function bulk_operation_exception_returns_failed_documents(): void
    {
        $failedDocs = [
            ['id' => '1', 'index' => 'books', 'error' => ['type' => 'mapper_parsing_exception']],
            ['id' => '2', 'index' => 'books', 'error' => ['type' => 'version_conflict']],
        ];
        $e = new BulkOperationException($failedDocs);

        $this->assertSame($failedDocs, $e->getFailedDocuments());
        $this->assertCount(2, $e->getFailedDocuments());
    }

    #[Test]
    public function bulk_operation_exception_empty_documents(): void
    {
        $e = new BulkOperationException([]);

        $this->assertSame([], $e->getFailedDocuments());
        $this->assertStringContainsString('0 document(s)', $e->getMessage());
    }

    #[Test]
    public function invalid_query_exception_extends_invalid_argument(): void
    {
        $e = new InvalidQueryException('Query validation failed');
        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        $this->assertSame('Query validation failed', $e->getMessage());
    }

    #[Test]
    public function invalid_search_result_exception_extends_search_exception(): void
    {
        $e = new InvalidSearchResultException('Invalid result');
        $this->assertInstanceOf(SearchException::class, $e);
        $this->assertSame('Invalid result', $e->getMessage());
    }

    #[Test]
    public function invalid_search_result_exception_missing_total_hits_factory(): void
    {
        $e = InvalidSearchResultException::missingTotalHits();

        $this->assertInstanceOf(InvalidSearchResultException::class, $e);
        $this->assertStringContainsString('total hits', $e->getMessage());
        $this->assertStringContainsString('tracked', $e->getMessage());
    }

    #[Test]
    public function duplicate_keyed_clause_exception_extends_invalid_argument(): void
    {
        $e = new DuplicateKeyedClauseException('must', 'my-key');
        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
    }

    #[Test]
    public function duplicate_keyed_clause_exception_contains_section_and_key(): void
    {
        $e = new DuplicateKeyedClauseException('filter', 'status-filter');

        $this->assertStringContainsString('filter', $e->getMessage());
        $this->assertStringContainsString('status-filter', $e->getMessage());
    }

    #[Test]
    public function incompatible_search_connection_exception_extends_invalid_argument(): void
    {
        $e = new IncompatibleSearchConnectionException(
            'App\\Models\\Book',
            'default',
            'App\\Models\\Author',
            'analytics',
        );

        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        $this->assertStringContainsString('App\\Models\\Book', $e->getMessage());
        $this->assertStringContainsString('App\\Models\\Author', $e->getMessage());
    }

    #[Test]
    public function ambiguous_joined_index_exception_extends_invalid_argument(): void
    {
        $e = new AmbiguousJoinedIndexException(
            'books',
            'App\\Models\\Book',
            'App\\Models\\LegacyBook',
        );

        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        $this->assertStringContainsString('books', $e->getMessage());
        $this->assertStringContainsString('App\\Models\\Book', $e->getMessage());
        $this->assertStringContainsString('App\\Models\\LegacyBook', $e->getMessage());
    }
}
