<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\MonitorType;
use App\Models\Monitor;

final class Checker
{
    public function __construct(
        private readonly HttpChecker $http,
        private readonly PingChecker $ping,
        private readonly TcpChecker $tcp,
        private readonly DnsChecker $dns,
        private readonly TlsChecker $tls,
        private readonly HeartbeatChecker $heartbeat,
        private readonly UdpChecker $udp,
        private readonly WebSocketChecker $webSocket,
    ) {}

    public function check(Monitor $monitor): ProbeResult
    {
        $monitor->loadMissing('conditions');

        return match ($monitor->type) {
            MonitorType::Http => $this->http->check($monitor),
            MonitorType::Ping => $this->ping->check($monitor),
            MonitorType::Tcp => $this->tcp->check($monitor),
            MonitorType::Dns => $this->dns->check($monitor),
            MonitorType::Tls => $this->tls->check($monitor),
            MonitorType::Heartbeat => $this->heartbeat->check($monitor),
            MonitorType::Udp => $this->udp->check($monitor),
            MonitorType::WebSocket => $this->webSocket->check($monitor),
        };
    }
}
