<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\ApiManagedUi;
use App\Support\ApiManaged;
use Filament\Actions\Action;

trait AbortsApiManagedCreate
{
    use ShowsApiManagedNotice;

    public function create(bool $another = false): void
    {
        ApiManaged::abortIfEnabled();

        parent::create($another);
    }

    protected function getCreateFormAction(): Action
    {
        return ApiManagedUi::lock(parent::getCreateFormAction());
    }
}
