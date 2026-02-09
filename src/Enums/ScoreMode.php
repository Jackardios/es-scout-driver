<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum ScoreMode: string
{
    case Avg = 'avg';
    case Max = 'max';
    case Min = 'min';
    case Sum = 'sum';
    case None = 'none';
}
