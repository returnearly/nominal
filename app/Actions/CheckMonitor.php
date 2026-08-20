<?php

declare(strict_types=1);

namespace App\Actions;

use App\Checking\ProbeResult;
use App\Enums\MonitorType;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class CheckMonitor implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private CheckHttp $http,
        private CheckPing $ping,
        private CheckTcp $tcp,
        private CheckDns $dns,
        private CheckTls $tls,
        private CheckHeartbeat $heartbeat,
        private CheckUdp $udp,
        private CheckWebSocket $webSocket,
    ) {}

    public function handle(Monitor $monitor): ProbeResult
    {
        $monitor->loadMissing('conditions');

        return match ($monitor->type) {
            MonitorType::Http, MonitorType::GraphQL => $this->http->handle($monitor),
            MonitorType::Ping => $this->ping->handle($monitor),
            MonitorType::Tcp => $this->tcp->handle($monitor),
            MonitorType::Dns => $this->dns->handle($monitor),
            MonitorType::Tls => $this->tls->handle($monitor),
            MonitorType::Heartbeat => $this->heartbeat->handle($monitor),
            MonitorType::Udp => $this->udp->handle($monitor),
            MonitorType::WebSocket => $this->webSocket->handle($monitor),
        };
    }
}
