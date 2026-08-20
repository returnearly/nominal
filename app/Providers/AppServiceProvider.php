<?php

declare(strict_types=1);

namespace App\Providers;

use App\Checking\DnsTransport;
use App\Checking\IcmpThenTcpPingTransport;
use App\Checking\OpensslCertificateReader;
use App\Checking\PhpDnsTransport;
use App\Checking\PhpStreamTcpTransport;
use App\Checking\PhpStreamTlsTransport;
use App\Checking\PingTransport;
use App\Checking\TcpTransport;
use App\Checking\TlsCertificateReader;
use App\Checking\TlsTransport;
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
    }

    public function boot(): void
    {
        //
    }
}
