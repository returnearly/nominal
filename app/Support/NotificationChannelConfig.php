<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\NotificationChannelType;
use ArrayObject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Validator;

final class NotificationChannelConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function from(mixed $config): array
    {
        if ($config instanceof Arrayable) {
            $config = $config->toArray();
        } elseif ($config instanceof ArrayObject) {
            $config = $config->getArrayCopy();
        }

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, string>
     */
    public static function normalize(NotificationChannelType $type, mixed $config): array
    {
        $config = self::from($config);
        $normalized = [];

        foreach ($type->fields() as $field) {
            $value = self::string($config[$field->key] ?? null);

            if ($value === null) {
                foreach ($field->aliases as $alias) {
                    $value = self::string($config[$alias] ?? null);

                    if ($value !== null) {
                        break;
                    }
                }
            }

            if ($value === null) {
                continue;
            }

            $normalized[$field->key] = $value;
        }

        return $normalized;
    }

    /**
     * Copy aliases onto canonical keys so the visible form field is filled.
     *
     * @return array<string, mixed>
     */
    public static function forForm(NotificationChannelType $type, mixed $config): array
    {
        $config = self::from($config);

        foreach ($type->fields() as $field) {
            if (self::string($config[$field->key] ?? null) !== null) {
                continue;
            }

            foreach ($field->aliases as $alias) {
                $value = self::string($config[$alias] ?? null);

                if ($value === null) {
                    continue;
                }

                $config[$field->key] = $value;
                break;
            }
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function assertValid(NotificationChannelType $type, array $config): void
    {
        $rules = [];
        $attributes = [];

        foreach ($type->fields() as $field) {
            $path = 'config.'.$field->key;
            $rules[$path] = $field->rules();
            $attributes[$path] = strtolower($field->label);
        }

        Validator::make(['config' => $config], $rules, [], $attributes)->validate();
    }

    public static function destination(NotificationChannelType $type, mixed $config): ?string
    {
        $config = self::normalize($type, $config);

        return match ($type) {
            NotificationChannelType::Mail => self::mailDestination($config),
            NotificationChannelType::Pagerduty => isset($config['routing_key']) ? 'Routing key configured' : null,
            default => self::host($config['webhook_url'] ?? $config['url'] ?? null),
        };
    }

    /**
     * Unique config keys across types, in enum order. Shared keys keep the first kind.
     *
     * @return array<string, 'email'|'url'|'password'|'text'|'integer'|'select'>
     */
    public static function formKeys(): array
    {
        $keys = [];

        foreach (NotificationChannelType::cases() as $type) {
            foreach ($type->fields() as $field) {
                $keys[$field->key] ??= $field->kind;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function mailDestination(array $config): ?string
    {
        $to = $config['to'] ?? null;

        if (! is_string($to) || $to === '') {
            return null;
        }

        $host = $config['host'] ?? null;

        return is_string($host) && $host !== '' ? $to.' via '.$host : $to;
    }

    private static function host(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $url;
    }

    private static function string(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
