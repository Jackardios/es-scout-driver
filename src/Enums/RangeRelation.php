<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum RangeRelation: string
{
    case Intersects = 'intersects';
    case Contains = 'contains';
    case Within = 'within';
}
