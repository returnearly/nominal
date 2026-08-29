<?php

declare(strict_types=1);

namespace App\Checking;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class WhoisClient
{
    private const IanaServer = 'whois.iana.org';

    /**
     * @var list<string>
     */
    private const TldsWithoutExpiration = [
        'at',
        'be',
        'ch',
        'co.at',
        'de',
        'eu',
        'nl',
        'or.at',
    ];

    /**
     * @var array<string, string>
     */
    private const SeededReferrals = [
        'com' => 'whois.verisign-grs.com',
        'io' => 'whois.nic.io',
        'net' => 'whois.verisign-grs.com',
        'org' => 'whois.publicinterestregistry.org',
        'sh' => 'whois.nic.sh',
        'uk' => 'whois.nic.uk',
        'mx' => 'whois.mx',
        'ro' => 'whois.rotld.ro',
        'kr' => 'whois.krnic.net',
    ];

    /**
     * @var list<string>
     */
    private const DateFormats = [
        DateTimeInterface::RFC3339,
        DateTimeInterface::RFC3339_EXTENDED,
        'Y-m-d H:i:s',
        'Y-m-d H:i:s T',
        'Y-m-d',
        'd-M-Y H:i:s T',
        'd-M-Y',
        'd.m.Y',
        'd/m/Y H:i:s',
        'Y. m. d.',
        'Y.m.d',
        'Ymd',
    ];

    public function __construct(private WhoisTransport $transport) {}

    public function expirationDate(string $hostname, int $timeoutSeconds = 10): ?DateTimeImmutable
    {
        $text = $this->query($hostname, $timeoutSeconds);

        if ($text === null) {
            return null;
        }

        return $this->parse($hostname, $text);
    }

    public function query(string $hostname, int $timeoutSeconds = 10): ?string
    {
        $tld = $this->tld($hostname);

        if ($tld === null || $this->tldOmitsExpiration($tld, $hostname)) {
            return null;
        }

        try {
            if ($tld === 'ua') {
                $uaTld = $this->uaTld($hostname);

                return $this->transport->query('whois.'.$uaTld, $hostname, $timeoutSeconds);
            }

            $referral = $this->referral($tld);

            if ($referral !== null) {
                return $this->transport->query($referral, $hostname, $timeoutSeconds);
            }

            $iana = $this->transport->query(self::IanaServer, $tld, $timeoutSeconds);
            $whoisServer = $this->referralFromIana($iana);

            if ($whoisServer === null) {
                return $iana;
            }

            $this->rememberReferral($tld, $whoisServer);

            return $this->transport->query($whoisServer, $hostname, $timeoutSeconds);
        } catch (Throwable) {
            return null;
        }
    }

    public function parse(string $hostname, string $text): ?DateTimeImmutable
    {
        $expiration = null;

        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            $line = trim($line);
            $separator = strpos($line, ':');

            if ($separator === false) {
                continue;
            }

            $key = strtolower(trim(substr($line, 0, $separator)));
            $value = trim(substr($line, $separator + 1));

            if ($value === '') {
                continue;
            }

            if (str_contains($key, 'expir')) {
                $expiration = $this->parseDate($hostname, $value) ?? $expiration;
            } elseif ($key === 'paid-till' && $this->suffixIs($hostname, ['ru', 'su'])) {
                $expiration = $this->parseDate($hostname, $value) ?? $expiration;
            }
        }

        return $expiration;
    }

    private function parseDate(string $hostname, string $value): ?DateTimeImmutable
    {
        $formats = match (true) {
            str_ends_with($hostname, '.pp.ua') => ['d-M-Y H:i:s T'],
            str_ends_with($hostname, '.ua') => ['Y-m-d H:i:sP', 'Y-m-d H:i:s'],
            str_ends_with($hostname, '.uk') => ['d-M-Y'],
            str_ends_with($hostname, '.cz') => ['d.m.Y'],
            str_ends_with($hostname, '.im') => ['d/m/Y H:i:s'],
            str_ends_with($hostname, '.br') => ['Ymd'],
            str_ends_with($hostname, '.cn') => ['Y-m-d H:i:s'],
            str_ends_with($hostname, '.kr') => ['Y. m. d.'],
            $this->suffixIs($hostname, ['mx', 'lt', 'ro']) => ['Y-m-d'],
            default => self::DateFormats,
        };

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value)
                ?: DateTimeImmutable::createFromFormat('!'.$format, strtoupper($value));

            if ($parsed instanceof DateTimeImmutable) {
                return $parsed;
            }
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function referral(string $tld): ?string
    {
        $cached = Cache::get($this->referralKey($tld));

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return self::SeededReferrals[$tld] ?? null;
    }

    private function rememberReferral(string $tld, string $server): void
    {
        Cache::put($this->referralKey($tld), $server, now()->addDays(30));
    }

    private function referralKey(string $tld): string
    {
        return 'nominal:whois:referral:'.$tld;
    }

    private function referralFromIana(string $text): ?string
    {
        if (preg_match('/^whois:\s*(\S+)/mi', $text, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function tld(string $hostname): ?string
    {
        $parts = explode('.', $hostname);

        if (count($parts) < 2) {
            return null;
        }

        return strtolower((string) array_pop($parts));
    }

    private function uaTld(string $hostname): string
    {
        $parts = explode('.', $hostname);

        if (count($parts) > 2 && strlen($parts[count($parts) - 2]) < 4) {
            return $parts[count($parts) - 2].'.'.$parts[count($parts) - 1];
        }

        return 'ua';
    }

    private function tldOmitsExpiration(string $tld, string $hostname): bool
    {
        foreach (self::TldsWithoutExpiration as $blocked) {
            if ($tld === $blocked || str_ends_with($hostname, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $suffixes
     */
    private function suffixIs(string $hostname, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (str_ends_with($hostname, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
