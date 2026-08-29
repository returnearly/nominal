<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use App\Support\EnumValue;
use App\Support\NotificationChannelConfig;
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
        $type = $this->type($input['type'] ?? $channel->type);
        $config = $this->config($type, $input, $channel);

        $config = NotificationChannelConfig::normalize($type, $config);
        NotificationChannelConfig::assertValid($type, $config);

        $channel->fill([
            'name' => $input['name'] ?? $channel->name,
            'type' => $type,
            'config' => $config,
        ]);

        $channel->save();

        return $channel->fresh() ?? $channel;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function config(NotificationChannelType $type, array $input, NotificationChannel $channel): array
    {
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
