<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\ApiManaged;
use Filament\Actions\Action;

final class ApiManagedUi
{
    /**
     * @template T of Action
     *
     * @param  T  $action
     * @return T
     */
    public static function lock(Action $action): Action
    {
        return $action
            ->disabled(ApiManaged::enabled(...))
            ->tooltip(ApiManaged::notice(...));
    }

    /**
     * @template T of Action
     *
     * @param  T  $action
     * @return T
     */
    public static function lockWrite(Action $action): Action
    {
        return self::lock($action)
            ->before(function (): void {
                ApiManaged::abortIfEnabled();
            });
    }
}
