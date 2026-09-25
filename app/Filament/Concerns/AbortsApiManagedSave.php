<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\ApiManagedUi;
use App\Support\ApiManaged;
use Filament\Actions\Action;

trait AbortsApiManagedSave
{
    use ShowsApiManagedNotice;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        ApiManaged::abortIfEnabled();

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function getSaveFormAction(): Action
    {
        return ApiManagedUi::lock(parent::getSaveFormAction());
    }
}
