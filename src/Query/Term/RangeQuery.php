<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

use DateTimeInterface;
use Jackardios\EsScoutDriver\Enums\RangeRelation;
use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class RangeQuery implements QueryInterface
{
    use HasBoost;

    private string|int|float|null $gt = null;
    private string|int|float|null $gte = null;
    private string|int|float|null $lt = null;
    private string|int|float|null $lte = null;
    private ?string $format = null;
    private ?string $relation = null;
    private ?string $timeZone = null;

    public function __construct(
        private string $field,
    ) {}

    public function gt(string|int|float|DateTimeInterface $gt): self
    {
        $this->gt = self::bound($gt);
        return $this;
    }

    public function gte(string|int|float|DateTimeInterface $gte): self
    {
        $this->gte = self::bound($gte);
        return $this;
    }

    public function lt(string|int|float|DateTimeInterface $lt): self
    {
        $this->lt = self::bound($lt);
        return $this;
    }

    public function lte(string|int|float|DateTimeInterface $lte): self
    {
        $this->lte = self::bound($lte);
        return $this;
    }

    /**
     * A date is sent as ISO 8601 with milliseconds and its UTC offset (2024-01-01T10:00:00.123+03:00): the instant
     * itself, in the shape the Elasticsearch reference gives for strict_date_optional_time, the default date format.
     * Microseconds are cut off, as a date field stores milliseconds; for a date_nanos field pass a string. A field
     * mapped with another format needs format('strict_date_optional_time') on the query, or a string in its format.
     */
    private static function bound(string|int|float|DateTimeInterface $bound): string|int|float
    {
        return $bound instanceof DateTimeInterface ? $bound->format('Y-m-d\TH:i:s.vP') : $bound;
    }

    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    public function relation(RangeRelation|string $relation): self
    {
        $this->relation = $relation instanceof RangeRelation ? $relation->value : $relation;
        return $this;
    }

    public function timeZone(string $timeZone): self
    {
        $this->timeZone = $timeZone;
        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->gt === null && $this->gte === null && $this->lt === null && $this->lte === null) {
            throw new InvalidQueryException('RangeQuery requires at least one bound (gt, gte, lt, or lte)');
        }

        $params = [];

        if ($this->gt !== null) {
            $params['gt'] = $this->gt;
        }

        if ($this->gte !== null) {
            $params['gte'] = $this->gte;
        }

        if ($this->lt !== null) {
            $params['lt'] = $this->lt;
        }

        if ($this->lte !== null) {
            $params['lte'] = $this->lte;
        }

        if ($this->format !== null) {
            $params['format'] = $this->format;
        }

        if ($this->relation !== null) {
            $params['relation'] = $this->relation;
        }

        if ($this->timeZone !== null) {
            $params['time_zone'] = $this->timeZone;
        }

        $this->applyBoost($params);

        return ['range' => [$this->field => $params]];
    }
}
