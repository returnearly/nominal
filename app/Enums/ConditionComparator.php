<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConditionComparator: string implements HasLabel
{
    case Equal = '==';
    case NotEqual = '!=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';

    public function getLabel(): string
    {
        return $this->value;
    }
}
