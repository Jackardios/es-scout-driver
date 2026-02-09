<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum BoostMode: string
{
    case Multiply = 'multiply';
    case Replace = 'replace';
    case Sum = 'sum';
    case Avg = 'avg';
    case Max = 'max';
    case Min = 'min';
}
