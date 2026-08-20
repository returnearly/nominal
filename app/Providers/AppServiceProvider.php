<?php

declare(strict_types=1);

namespace App\Providers;

use App\Checking\DnsTransport;
use App\Checking\IcmpThenTcpPingTransport;
use App\Checking\MysqlTransport;
use App\Checking\OpensslCertificateReader;
use App\Checking\PdoMysqlTransport;
use App\Checking\PdoPostgresTransport;
use App\Checking\PhpDnsTransport;
use App\Checking\PhpStreamDialer;
use App\Checking\PhpStreamTcpTransport;
use App\Checking\PhpStreamTlsTransport;
use App\Checking\PhpStreamUdpTransport;
use App\Checking\PhpStreamWebSocketTransport;
use App\Checking\PingTransport;
use App\Checking\PostgresTransport;
use App\Checking\RedisRespTransport;
use App\Checking\RedisTransport;
use App\Checking\StreamDialer;
use App\Checking\TcpTransport;
use App\Checking\TlsCertificateReader;
use App\Checking\TlsTransport;
use App\Checking\UdpTransport;
use App\Checking\WebSocketTransport;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TlsCertificateReader::class, OpensslCertificateReader::class);
        $this->app->bind(PingTransport::class, IcmpThenTcpPingTransport::class);
        $this->app->bind(TcpTransport::class, PhpStreamTcpTransport::class);
        $this->app->bind(DnsTransport::class, PhpDnsTransport::class);
        $this->app->bind(TlsTransport::class, PhpStreamTlsTransport::class);
        $this->app->bind(UdpTransport::class, PhpStreamUdpTransport::class);
        $this->app->bind(WebSocketTransport::class, PhpStreamWebSocketTransport::class);
        $this->app->bind(StreamDialer::class, PhpStreamDialer::class);
        $this->app->bind(MysqlTransport::class, PdoMysqlTransport::class);
        $this->app->bind(RedisTransport::class, RedisRespTransport::class);
        $this->app->bind(PostgresTransport::class, PdoPostgresTransport::class);
    }

    public function boot(): void
    {
        //
    }
}
