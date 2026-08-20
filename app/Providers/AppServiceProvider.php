<?php

declare(strict_types=1);

namespace App\Providers;

use App\Checking\DnsTransport;
use App\Checking\DomainExpirationReader;
use App\Checking\IcmpThenTcpPingTransport;
use App\Checking\OpensslCertificateReader;
use App\Checking\PhpDnsTransport;
use App\Checking\PhpStreamDialer;
use App\Checking\PhpStreamTcpTransport;
use App\Checking\PhpStreamTlsTransport;
use App\Checking\PhpStreamUdpTransport;
use App\Checking\PhpStreamWebSocketTransport;
use App\Checking\PhpWhoisTransport;
use App\Checking\PingTransport;
use App\Checking\RdapThenWhoisDomainExpirationReader;
use App\Checking\StreamDialer;
use App\Checking\TcpTransport;
use App\Checking\TlsCertificateReader;
use App\Checking\TlsTransport;
use App\Checking\UdpTransport;
use App\Checking\WebSocketTransport;
use App\Checking\WhoisClient;
use App\Checking\WhoisTransport;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
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
        $this->app->bind(WhoisTransport::class, PhpWhoisTransport::class);
        $this->app->bind(DomainExpirationReader::class, function ($app): RdapThenWhoisDomainExpirationReader {
            return new RdapThenWhoisDomainExpirationReader(
                $app->make(WhoisClient::class),
                new Client([
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::ALLOW_REDIRECTS => true,
                ]),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
