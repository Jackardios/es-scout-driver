<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum ValidationMethod: string
{
    case Strict = 'strict';
    case IgnoreMalformed = 'ignore_malformed';
    case Coerce = 'coerce';
}
