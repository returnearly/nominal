<?php

declare(strict_types=1);

namespace App\Conditions;

use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use App\Enums\MonitorType;
use BackedEnum;
use Illuminate\Support\Str;

final class ConditionExpression
{
    /**
     * @return array{placeholder: string, path: string, comparator: string, value: string}
     */
    public static function parse(?string $expression): array
    {
        $expression = trim((string) $expression);

        if ($expression === '') {
            return self::newItem(MonitorType::Http);
        }

        try {
            [$left, $operator, $right] = self::split($expression);
        } catch (InvalidConditionException) {
            return [
                'placeholder' => $expression,
                'path' => '',
                'comparator' => ConditionComparator::Equal->value,
                'value' => '',
            ];
        }

        $placeholder = $left;
        $path = '';

        if (preg_match('/^(\[[A-Z_]+\])(.*)$/', $left, $matches) === 1) {
            $placeholder = $matches[1];
            $path = $matches[2];
        }

        return [
            'placeholder' => $placeholder,
            'path' => $path,
            'comparator' => $operator,
            'value' => $right,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function compose(array $data): string
    {
        $placeholder = self::string($data['placeholder'] ?? '');
        $path = self::normalizePath($placeholder, self::string($data['path'] ?? ''));
        $comparator = self::string($data['comparator'] ?? ConditionComparator::Equal->value);
        $value = self::string($data['value'] ?? '');

        return trim("{$placeholder}{$path} {$comparator} {$value}");
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toRecord(array $data): array
    {
        $data['expression'] = self::compose($data);
        unset($data['placeholder'], $data['path'], $data['comparator'], $data['value']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toForm(array $data): array
    {
        return [
            ...$data,
            ...self::parse($data['expression'] ?? null),
        ];
    }

    /**
     * @return list<array{placeholder: string, path: string, comparator: string, value: string}>
     */
    public static function defaultsForType(mixed $type): array
    {
        return match (self::type($type)) {
            MonitorType::Dns => [
                [
                    'placeholder' => ConditionPlaceholder::DnsRcode->value,
                    'path' => '',
                    'comparator' => ConditionComparator::Equal->value,
                    'value' => 'NOERROR',
                ],
            ],
            MonitorType::Heartbeat => [
                [
                    'placeholder' => ConditionPlaceholder::Connected->value,
                    'path' => '',
                    'comparator' => ConditionComparator::Equal->value,
                    'value' => 'true',
                ],
            ],
            MonitorType::Tls => [
                [
                    'placeholder' => ConditionPlaceholder::Connected->value,
                    'path' => '',
                    'comparator' => ConditionComparator::Equal->value,
                    'value' => 'true',
                ],
                [
                    'placeholder' => ConditionPlaceholder::CertificateExpiration->value,
                    'path' => '',
                    'comparator' => ConditionComparator::GreaterThan->value,
                    'value' => '48h',
                ],
            ],
            MonitorType::Ping, MonitorType::Tcp, MonitorType::Udp => [
                [
                    'placeholder' => ConditionPlaceholder::Connected->value,
                    'path' => '',
                    'comparator' => ConditionComparator::Equal->value,
                    'value' => 'true',
                ],
                [
                    'placeholder' => ConditionPlaceholder::ResponseTime->value,
                    'path' => '',
                    'comparator' => ConditionComparator::LessThan->value,
                    'value' => '50',
                ],
            ],
            default => [
                [
                    'placeholder' => ConditionPlaceholder::Status->value,
                    'path' => '',
                    'comparator' => ConditionComparator::GreaterThanOrEqual->value,
                    'value' => '200',
                ],
                [
                    'placeholder' => ConditionPlaceholder::Status->value,
                    'path' => '',
                    'comparator' => ConditionComparator::LessThanOrEqual->value,
                    'value' => '299',
                ],
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function defaultExpressions(mixed $type): array
    {
        return array_map(self::compose(...), self::defaultsForType($type));
    }

    /**
     * @return array<string, array{placeholder: string, path: string, comparator: string, value: string}>
     */
    public static function defaultFormState(mixed $type): array
    {
        $items = [];

        foreach (self::defaultsForType($type) as $item) {
            $items[(string) Str::uuid()] = $item;
        }

        return $items;
    }

    /**
     * @return array{placeholder: string, path: string, comparator: string, value: string}
     */
    public static function newItem(mixed $type): array
    {
        return self::defaultsForType($type)[0];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function split(string $expression): array
    {
        $operators = ['==', '!=', '<=', '>=', '<', '>'];
        $depth = 0;
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $character = $expression[$i];

            if ($character === '(') {
                $depth++;

                continue;
            }

            if ($character === ')') {
                $depth--;

                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            foreach ($operators as $operator) {
                if (substr($expression, $i, strlen($operator)) === $operator) {
                    return [
                        trim(substr($expression, 0, $i)),
                        $operator,
                        trim(substr($expression, $i + strlen($operator))),
                    ];
                }
            }
        }

        throw new InvalidConditionException("Missing comparator in [{$expression}].");
    }

    private static function normalizePath(string $placeholder, string $path): string
    {
        $path = trim($path);

        if ($placeholder !== ConditionPlaceholder::Body->value || $path === '') {
            return '';
        }

        if (! str_starts_with($path, '.') && ! str_starts_with($path, '[')) {
            return '.'.$path;
        }

        return $path;
    }

    private static function string(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return trim((string) $value->value);
        }

        return trim((string) $value);
    }

    private static function type(mixed $type): MonitorType
    {
        if ($type instanceof MonitorType) {
            return $type;
        }

        return MonitorType::tryFrom((string) $type) ?? MonitorType::Http;
    }
}
