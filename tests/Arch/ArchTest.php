<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;

arch('strict types are used')
    ->expect('App')
    ->toUseStrictTypes();

arch('debug helpers are not used')
    ->expect('App')
    ->not->toUse(['die', 'dd', 'dump', 'ray']);

arch('enums are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('actions are final and only expose handle')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toBeFinal()
    ->toImplement(ActionsPatternInterface::class)
    ->toHaveMethod('handle')
    ->not->toHavePublicMethodsBesides(['__construct', 'handle']);

arch('jobs are final queued handlers')
    ->expect('App\Jobs')
    ->toBeClasses()
    ->toBeFinal()
    ->toImplement(ShouldQueue::class)
    ->toHaveMethod('handle')
    ->not->toHavePublicMethodsBesides(['__construct', 'handle']);

arch('models extend eloquent')
    ->expect('App\Models')
    ->toBeClasses()
    ->toExtend(Model::class);
