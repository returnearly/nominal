<?php

declare(strict_types=1);

namespace App\Providers;

use App\Checking\IcmpThenTcpPingTransport;
use App\Checking\OpensslCertificateReader;
use App\Checking\PingTransport;
use App\Checking\TlsCertificateReader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TlsCertificateReader::class, OpensslCertificateReader::class);
        $this->app->bind(PingTransport::class, IcmpThenTcpPingTransport::class);
    }

    public function boot(): void
    {
        //
    }
}
