<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum Operator: string
{
    case And = 'and';
    case Or = 'or';
}
