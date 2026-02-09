<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum SoftDeleteMode: string
{
    case ExcludeTrashed = 'exclude';
    case WithTrashed = 'with';
    case OnlyTrashed = 'only';
}
