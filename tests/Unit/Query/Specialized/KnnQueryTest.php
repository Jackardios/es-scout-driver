<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Specialized\KnnQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KnnQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_knn_query_without_num_candidates(): void
    {
        $query = new KnnQuery('embedding', [0.1, 0.2, 0.3], 10);

        $this->assertSame([
            'knn' => [
                'field' => 'embedding',
                'query_vector' => [0.1, 0.2, 0.3],
                'k' => 10,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_leaves_num_candidates_to_elasticsearch_for_a_large_k(): void
    {
        $query = new KnnQuery('embedding', [0.1, 0.2, 0.3], 6000);

        $this->assertArrayNotHasKey('num_candidates', $query->toArray()['knn']);
    }

    #[Test]
    public function it_builds_knn_query_with_custom_num_candidates(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->numCandidates(200);

        $this->assertSame([
            'knn' => [
                'field' => 'embedding',
                'query_vector' => [0.1, 0.2, 0.3],
                'k' => 10,
                'num_candidates' => 200,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_knn_query_with_similarity(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->similarity(0.8);

        $result = $query->toArray();
        $this->assertSame(0.8, $result['knn']['similarity']);
    }

    #[Test]
    public function it_builds_knn_query_with_filter_query_interface(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->filter(new TermQuery('status', 'active'));

        $result = $query->toArray();
        $this->assertSame(['term' => ['status' => ['value' => 'active']]], $result['knn']['filter']);
    }

    #[Test]
    public function it_builds_knn_query_with_filter_array(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->filter(['term' => ['status' => 'published']]);

        $result = $query->toArray();
        $this->assertSame(['term' => ['status' => 'published']], $result['knn']['filter']);
    }

    #[Test]
    public function it_builds_knn_query_with_boost(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->boost(2.0);

        $result = $query->toArray();
        $this->assertSame(2.0, $result['knn']['boost']);
    }

    #[Test]
    public function it_builds_knn_query_with_all_options(): void
    {
        $query = (new KnnQuery('embedding', [0.5, 0.5], 20))
            ->numCandidates(150)
            ->similarity(0.9)
            ->filter(new TermQuery('category', 'tech'))
            ->boost(1.5);

        $this->assertSame([
            'knn' => [
                'field' => 'embedding',
                'query_vector' => [0.5, 0.5],
                'k' => 20,
                'num_candidates' => 150,
                'similarity' => 0.9,
                'filter' => ['term' => ['category' => ['value' => 'tech']]],
                'boost' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new KnnQuery('embedding', [0.1, 0.2], 5);

        $this->assertSame($query, $query->numCandidates(100));
        $this->assertSame($query, $query->similarity(0.8));
        $this->assertSame($query, $query->filter(['term' => ['x' => 'y']]));
        $this->assertSame($query, $query->boost(1.0));
    }

    #[Test]
    public function it_accepts_num_candidates_of_10000(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))->numCandidates(10000);

        $this->assertSame(10000, $query->toArray()['knn']['num_candidates']);
    }

    #[Test]
    public function it_throws_exception_for_num_candidates_above_10000(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires numCandidates to be at most 10000');

        (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))->numCandidates(10001);
    }

    #[Test]
    public function it_throws_exception_for_num_candidates_below_k(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires numCandidates to be greater than or equal to k');

        (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))->numCandidates(9);
    }

    #[Test]
    public function it_throws_exception_for_num_candidates_of_zero(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires numCandidates to be greater than 0');

        (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))->numCandidates(0);
    }

    #[Test]
    public function it_has_no_inner_hits_setter(): void
    {
        $this->assertFalse(method_exists(KnnQuery::class, 'innerHits'));
    }

    #[Test]
    public function it_throws_exception_for_k_equal_to_zero_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires k to be greater than 0');

        new KnnQuery('embedding', [0.1, 0.2, 0.3], 0);
    }

    #[Test]
    public function it_throws_exception_for_negative_k_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires k to be greater than 0');

        new KnnQuery('embedding', [0.1, 0.2, 0.3], -5);
    }

    #[Test]
    public function it_throws_exception_for_empty_query_vector_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires a non-empty query vector');

        new KnnQuery('embedding', [], 10);
    }

    #[Test]
    public function it_serializes_a_keyed_query_vector_as_a_list(): void
    {
        $query = new KnnQuery('embedding', [1 => 0.1, 2 => 0.2], 1);

        $this->assertSame([0.1, 0.2], $query->toArray()['knn']['query_vector']);
    }
}
