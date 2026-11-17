<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Support\EnumValue;
use App\Support\NotificationChannelConfig;
use App\Support\WebPushVapid;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SaveNotificationChannel implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?NotificationChannel $channel = null): NotificationChannel
    {
        $channel ??= new NotificationChannel;
        $previousType = $channel->exists ? $channel->type : null;
        $type = $this->type($input['type'] ?? $channel->type);
        $config = $this->config($type, $input, $channel);

        $config = NotificationChannelConfig::normalize($type, $config);

        if ($type === NotificationChannelType::Browser) {
            $config = $this->browserConfig($config, $channel, $previousType);
        }

        NotificationChannelConfig::assertValid($type, $config);

        $attributes = [
            'name' => $input['name'] ?? $channel->name,
            'type' => $type,
            'config' => $config,
        ];

        if (array_key_exists('minimum_open_seconds', $input) || array_key_exists('minimumOpenSeconds', $input)) {
            $attributes['minimum_open_seconds'] = $this->minimumOpenSeconds(
                $input['minimum_open_seconds'] ?? $input['minimumOpenSeconds'],
            );
        } elseif (! $channel->exists) {
            $attributes['minimum_open_seconds'] = 0;
        }

        $channel->fill($attributes);
        $channel->save();

        if ($previousType === NotificationChannelType::Browser && $type !== NotificationChannelType::Browser) {
            $channel->pushSubscriptions()->delete();
        }

        return $channel->fresh() ?? $channel;
    }

    private function minimumOpenSeconds(mixed $value): int
    {
        $seconds = max(0, min(86400, (int) $value));

        return $seconds;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function config(NotificationChannelType $type, array $input, NotificationChannel $channel): array
    {
        if ($type === NotificationChannelType::Browser) {
            $typed = $this->typedInputs($input);

            if ($typed !== [] && ! isset($typed['browser'])) {
                throw ValidationException::withMessages([
                    array_key_first($typed) => 'Only the browser input can be used when type is Browser.',
                ]);
            }

            if (array_key_exists('config', $input) && is_array($input['config'])) {
                return $input['config'];
            }

            if ($channel->exists && $channel->type === NotificationChannelType::Browser) {
                return $channel->configArray();
            }

            return [];
        }

        $typed = $this->typedInputs($input);

        if ($typed !== []) {
            return $this->configFromTyped($type, $typed);
        }

        if (array_key_exists('config', $input)) {
            return is_array($input['config']) ? $input['config'] : [];
        }

        if ($channel->exists && $type === $channel->type) {
            return $channel->configArray();
        }

        throw ValidationException::withMessages([
            $this->field($type) => 'The '.$this->field($type).' input is required when type is '.$type->name.'.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function browserConfig(array $config, NotificationChannel $channel, ?NotificationChannelType $previousType): array
    {
        $existing = WebPushVapid::fromConfig(
            $previousType === NotificationChannelType::Browser
                ? $channel->configArray()
                : $config,
        );

        if ($existing !== null && $previousType === NotificationChannelType::Browser) {
            return $existing;
        }

        if ($existing !== null && WebPushVapid::fromConfig($config) !== null) {
            return $existing;
        }

        return WebPushVapid::generate(
            isset($config['vapid_subject']) && is_string($config['vapid_subject'])
                ? $config['vapid_subject']
                : null,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<string, mixed>>
     */
    private function typedInputs(array $input): array
    {
        $typed = [];

        foreach (NotificationChannelType::cases() as $type) {
            $field = $this->field($type);

            if (! array_key_exists($field, $input) || ! is_array($input[$field])) {
                continue;
            }

            $typed[$field] = $input[$field];
        }

        return $typed;
    }

    /**
     * @param  array<string, array<string, mixed>>  $typed
     * @return array<string, mixed>
     */
    private function configFromTyped(NotificationChannelType $type, array $typed): array
    {
        $expected = $this->field($type);

        foreach (array_keys($typed) as $field) {
            if ($field === $expected) {
                continue;
            }

            throw ValidationException::withMessages([
                $field => "The {$field} input cannot be used when type is {$type->name}.",
            ]);
        }

        if (! isset($typed[$expected])) {
            throw ValidationException::withMessages([
                $expected => "The {$expected} input is required when type is {$type->name}.",
            ]);
        }

        $config = [];

        foreach ($typed[$expected] as $key => $value) {
            $config[Str::snake((string) $key)] = $value;
        }

        return $config;
    }

    private function field(NotificationChannelType $type): string
    {
        return Str::camel($type->value);
    }

    private function type(mixed $type): NotificationChannelType
    {
        if ($type instanceof NotificationChannelType) {
            return $type;
        }

        return EnumValue::parse(NotificationChannelType::class, $type);
    }
}
