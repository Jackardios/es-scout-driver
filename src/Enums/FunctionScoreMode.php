<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum FunctionScoreMode: string
{
    case Multiply = 'multiply';
    case Sum = 'sum';
    case Avg = 'avg';
    case First = 'first';
    case Max = 'max';
    case Min = 'min';
}
