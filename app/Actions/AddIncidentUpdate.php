<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentUpdate;
use App\Support\EnumValue;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class AddIncidentUpdate implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Incident $incident, array $input): IncidentUpdate
    {
        $status = $input['status'] ?? $incident->status;
        $status = $status instanceof IncidentStatus
            ? $status
            : EnumValue::parse(IncidentStatus::class, $status);

        $update = $incident->updates()->create([
            'status' => $status,
            'message' => trim((string) $input['message']),
            'posted_at' => $input['postedAt'] ?? $input['posted_at'] ?? now(),
        ]);

        $incident->status = $status;

        if ($status->isResolved()) {
            $incident->resolved_at ??= now();
        } else {
            $incident->resolved_at = null;
        }

        $incident->save();

        return $update;
    }
}
