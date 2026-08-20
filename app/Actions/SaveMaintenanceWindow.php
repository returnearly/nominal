<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\MaintenanceWindow;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SaveMaintenanceWindow implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?MaintenanceWindow $window = null): MaintenanceWindow
    {
        $window ??= new MaintenanceWindow;
        $appliesToAll = (bool) ($input['appliesToAll'] ?? $input['applies_to_all'] ?? $window->applies_to_all ?? false);
        $startsAt = $this->timestamp($input['startsAt'] ?? $input['starts_at'] ?? $window->starts_at ?? now());
        $endsAt = array_key_exists('endsAt', $input) || array_key_exists('ends_at', $input)
            ? $this->timestamp($input['endsAt'] ?? $input['ends_at'] ?? null)
            : $window->ends_at;

        if ($startsAt === null) {
            throw ValidationException::withMessages([
                'starts_at' => 'A start time is required.',
            ]);
        }

        if ($endsAt !== null && $endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The end time must be after the start time.',
            ]);
        }

        $title = $input['title'] ?? $window->title;

        if (blank($title)) {
            throw ValidationException::withMessages([
                'title' => 'A title is required.',
            ]);
        }

        $monitorIds = $input['monitorIds'] ?? $input['monitor_ids'] ?? null;

        if (! $appliesToAll) {
            $monitorIds ??= $window->exists ? $window->monitors()->pluck('id')->all() : [];

            if ($monitorIds === []) {
                throw ValidationException::withMessages([
                    'monitorIds' => 'Select at least one monitor, or apply this window to all monitors.',
                ]);
            }
        }

        $window->fill([
            'title' => $title,
            'message' => array_key_exists('message', $input) ? ($input['message'] ?: null) : $window->message,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'applies_to_all' => $appliesToAll,
        ]);
        $window->save();

        if ($appliesToAll) {
            $window->monitors()->sync([]);
        } elseif (is_array($monitorIds)) {
            $window->monitors()->sync($monitorIds);
        }

        return $window->fresh(['monitors']) ?? $window;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
