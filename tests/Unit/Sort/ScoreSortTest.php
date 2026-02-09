<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Sort;

use Jackardios\EsScoutDriver\Enums\SortOrder;
use Jackardios\EsScoutDriver\Sort\ScoreSort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScoreSortTest extends TestCase
{
    #[Test]
    public function it_builds_score_sort_desc_by_default(): void
    {
        $sort = new ScoreSort();

        $this->assertSame(['_score' => 'desc'], $sort->toArray());
    }

    #[Test]
    public function it_builds_score_sort_asc(): void
    {
        $sort = (new ScoreSort())->asc();

        $this->assertSame(['_score' => 'asc'], $sort->toArray());
    }

    #[Test]
    public function it_accepts_sort_order_enum(): void
    {
        $sort = (new ScoreSort())->order(SortOrder::Asc);

        $this->assertSame(['_score' => 'asc'], $sort->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $sort = new ScoreSort();

        $this->assertSame($sort, $sort->asc());
        $this->assertSame($sort, $sort->desc());
        $this->assertSame($sort, $sort->order('asc'));
    }
}
