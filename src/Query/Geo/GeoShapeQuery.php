<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Geo;

use Jackardios\EsScoutDriver\Enums\GeoShapeRelation;
use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Concerns\HasIgnoreUnmapped;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class GeoShapeQuery implements QueryInterface
{
    use HasIgnoreUnmapped;

    /** @var array<string, mixed>|null */
    private ?array $shape = null;
    /** @var array<string, mixed>|null */
    private ?array $indexedShape = null;
    private ?string $relation = null;

    public function __construct(
        private string $field,
    ) {}

    /** @param array<string, mixed> $shape GeoJSON shape definition */
    public function shape(array $shape): self
    {
        $this->shape = $shape;
        return $this;
    }

    /** @param array<int, array<int, float>> $coordinates [[minLon, maxLat], [maxLon, minLat]] */
    public function envelope(array $coordinates): self
    {
        $this->shape = [
            'type' => 'envelope',
            'coordinates' => $coordinates,
        ];
        return $this;
    }

    /** @param array<int, array<int, float>> $coordinates Array of [lon, lat] pairs */
    public function polygon(array $coordinates): self
    {
        $this->shape = [
            'type' => 'polygon',
            'coordinates' => [$coordinates],
        ];
        return $this;
    }

    /** @param array<int, float> $coordinates [lon, lat] */
    public function point(array $coordinates): self
    {
        $this->shape = [
            'type' => 'point',
            'coordinates' => $coordinates,
        ];
        return $this;
    }

    public function circle(float $lon, float $lat, string $radius): self
    {
        $this->shape = [
            'type' => 'circle',
            'coordinates' => [$lon, $lat],
            'radius' => $radius,
        ];
        return $this;
    }

    public function indexedShape(string $index, string $id, string $path = 'shape'): self
    {
        $this->indexedShape = [
            'index' => $index,
            'id' => $id,
            'path' => $path,
        ];
        return $this;
    }

    public function relation(GeoShapeRelation|string $relation): self
    {
        $this->relation = $relation instanceof GeoShapeRelation ? $relation->value : $relation;
        return $this;
    }

    public function toArray(): array
    {
        if ($this->shape === null && $this->indexedShape === null) {
            throw new InvalidQueryException('GeoShapeQuery requires either shape or indexedShape');
        }

        $fieldParams = [];

        if ($this->shape !== null) {
            $fieldParams['shape'] = $this->shape;
        }

        if ($this->indexedShape !== null) {
            $fieldParams['indexed_shape'] = $this->indexedShape;
        }

        if ($this->relation !== null) {
            $fieldParams['relation'] = $this->relation;
        }

        $params = [
            $this->field => $fieldParams,
        ];

        $this->applyIgnoreUnmapped($params);

        return ['geo_shape' => $params];
    }
}
