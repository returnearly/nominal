<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DnsQueryType: string implements HasLabel
{
    case A = 'A';
    case AAAA = 'AAAA';
    case CNAME = 'CNAME';
    case MX = 'MX';
    case NS = 'NS';
    case PTR = 'PTR';
    case SRV = 'SRV';
    case TXT = 'TXT';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function wireType(): int
    {
        return match ($this) {
            self::A => 1,
            self::NS => 2,
            self::CNAME => 5,
            self::PTR => 12,
            self::MX => 15,
            self::TXT => 16,
            self::AAAA => 28,
            self::SRV => 33,
        };
    }
}
