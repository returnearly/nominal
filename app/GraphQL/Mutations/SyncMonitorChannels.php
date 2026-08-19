<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SyncMonitorChannels as SyncMonitorChannelsAction;
use App\Models\Monitor;

final class SyncMonitorChannels
{
    public function __construct(
        private readonly SyncMonitorChannelsAction $syncMonitorChannels,
    ) {}

    /**
     * @param  array{monitorId: string, channelIds: list<string>}  $args
     */
    public function __invoke(mixed $root, array $args): Monitor
    {
        $monitor = Monitor::query()->findOrFail($args['monitorId']);

        return $this->syncMonitorChannels->handle($monitor, $args['channelIds']);
    }
}
