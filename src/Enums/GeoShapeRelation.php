<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum GeoShapeRelation: string
{
    case Intersects = 'intersects';
    case Disjoint = 'disjoint';
    case Within = 'within';
    case Contains = 'contains';
}
