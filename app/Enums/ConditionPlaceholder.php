<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConditionPlaceholder: string implements HasLabel
{
    case Status = '[STATUS]';
    case Body = '[BODY]';
    case Connected = '[CONNECTED]';
    case ResponseTime = '[RESPONSE_TIME]';
    case Ip = '[IP]';
    case CertificateExpiration = '[CERTIFICATE_EXPIRATION]';
    case DnsRcode = '[DNS_RCODE]';

    public function getLabel(): string
    {
        return $this->value;
    }

    /**
     * @return list<ConditionComparator>
     */
    public function comparators(): array
    {
        return match ($this) {
            self::Connected, self::Ip, self::DnsRcode => [
                ConditionComparator::Equal,
                ConditionComparator::NotEqual,
            ],
            default => ConditionComparator::cases(),
        };
    }

    public function defaultComparator(): ConditionComparator
    {
        return match ($this) {
            self::ResponseTime => ConditionComparator::LessThan,
            self::CertificateExpiration => ConditionComparator::GreaterThan,
            default => $this->comparators()[0],
        };
    }

    public function defaultValue(): string
    {
        return match ($this) {
            self::Status => '200',
            self::Connected => 'true',
            self::ResponseTime => '50',
            self::CertificateExpiration => '48h',
            self::DnsRcode => 'NOERROR',
            self::Body, self::Ip => '',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(?string $current = null): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->value;
        }

        if (is_string($current) && $current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function comparatorOptions(mixed $placeholder, mixed $current = null): array
    {
        $placeholder = $placeholder instanceof self
            ? $placeholder
            : self::tryFrom(trim((string) $placeholder));

        $options = [];

        foreach ($placeholder?->comparators() ?? ConditionComparator::cases() as $comparator) {
            $options[$comparator->value] = $comparator->value;
        }

        $current = $current instanceof ConditionComparator ? $current->value : trim((string) $current);

        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options[$current] = $current;
        }

        return $options;
    }
}
