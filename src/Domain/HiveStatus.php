<?php

declare(strict_types=1);

namespace BeeBank\Domain;

enum HiveStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
}
