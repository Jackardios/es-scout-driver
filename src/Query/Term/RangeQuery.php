<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Term;

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

    public function gt(string|int|float $gt): self
    {
        $this->gt = $gt;
        return $this;
    }

    public function gte(string|int|float $gte): self
    {
        $this->gte = $gte;
        return $this;
    }

    public function lt(string|int|float $lt): self
    {
        $this->lt = $lt;
        return $this;
    }

    public function lte(string|int|float $lte): self
    {
        $this->lte = $lte;
        return $this;
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
