<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Joining;

use Jackardios\EsScoutDriver\Query\Joining\ParentIdQuery;
use Jackardios\EsScoutDriver\Support\Query;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParentIdQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_parent_id_query(): void
    {
        $query = new ParentIdQuery('my_child', 'parent-123');

        $this->assertSame([
            'parent_id' => [
                'type' => 'my_child',
                'id' => 'parent-123',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_supports_ignore_unmapped(): void
    {
        $query = (new ParentIdQuery('my_child', 'parent-123'))
            ->ignoreUnmapped();

        $this->assertSame([
            'parent_id' => [
                'type' => 'my_child',
                'id' => 'parent-123',
                'ignore_unmapped' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_supports_ignore_unmapped_false(): void
    {
        $query = (new ParentIdQuery('my_child', 'parent-123'))
            ->ignoreUnmapped(false);

        $this->assertSame([
            'parent_id' => [
                'type' => 'my_child',
                'id' => 'parent-123',
                'ignore_unmapped' => false,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_can_be_created_via_factory(): void
    {
        $query = Query::parentId('answer', 'question-1');

        $this->assertInstanceOf(ParentIdQuery::class, $query);
        $this->assertSame([
            'parent_id' => [
                'type' => 'answer',
                'id' => 'question-1',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new ParentIdQuery('my_child', 'parent-123');

        $this->assertSame($query, $query->ignoreUnmapped(true));
    }
}
