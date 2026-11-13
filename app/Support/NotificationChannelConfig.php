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

        if ($type === NotificationChannelType::Browser) {
            return self::normalizeBrowser($config);
        }

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
        if ($type === NotificationChannelType::Browser) {
            Validator::make(['config' => $config], [
                'config.vapid_public_key' => ['required', 'string'],
                'config.vapid_private_key' => ['required', 'string'],
                'config.vapid_subject' => ['required', 'string'],
            ], [], [
                'config.vapid_public_key' => 'vapid public key',
                'config.vapid_private_key' => 'vapid private key',
                'config.vapid_subject' => 'vapid subject',
            ])->validate();

            return;
        }

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
            NotificationChannelType::Browser => isset($config['vapid_public_key']) ? 'Browser push' : null,
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
     * @return array<string, string>
     */
    private static function normalizeBrowser(array $config): array
    {
        $normalized = [];

        foreach (['vapid_public_key', 'vapid_private_key', 'vapid_subject'] as $key) {
            $value = self::string($config[$key] ?? null);

            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
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
