<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\ApiManaged;
use Illuminate\Contracts\Support\Htmlable;

trait ShowsApiManagedNotice
{
    public function getSubheading(): string|Htmlable|null
    {
        return ApiManaged::notice() ?? parent::getSubheading();
    }
}
