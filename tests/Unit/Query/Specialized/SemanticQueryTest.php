<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Specialized\SemanticQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SemanticQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_semantic_query(): void
    {
        $query = new SemanticQuery('content_embedding', 'What is machine learning?');

        $this->assertSame([
            'semantic' => [
                'field' => 'content_embedding',
                'query' => 'What is machine learning?',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_semantic_query_with_boost(): void
    {
        $query = (new SemanticQuery('content_embedding', 'search query'))
            ->boost(2.0);

        $this->assertSame([
            'semantic' => [
                'field' => 'content_embedding',
                'query' => 'search query',
                'boost' => 2.0,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new SemanticQuery('field', 'query');

        $this->assertSame($query, $query->boost(1.5));
    }
}
