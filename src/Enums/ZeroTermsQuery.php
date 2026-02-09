<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Enums;

enum ZeroTermsQuery: string
{
    case None = 'none';
    case All = 'all';
}
