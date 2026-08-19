<?php

declare(strict_types=1);

namespace App\Enums;

enum IpFamily: string
{
    case Ipv4 = 'ipv4';
    case Ipv6 = 'ipv6';
    case Any = 'any';
}
