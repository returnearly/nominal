<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\IncidentImpact;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\StatusPage;
use App\Support\EnumValue;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SaveIncident implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?Incident $incident = null): Incident
    {
        $isNew = $incident === null;
        $incident ??= new Incident;

        $status = $input['status'] ?? $incident->status ?? IncidentStatus::Investigating;
        $status = $status instanceof IncidentStatus
            ? $status
            : EnumValue::parse(IncidentStatus::class, $status);

        $impact = $input['impact'] ?? $incident->impact ?? IncidentImpact::Minor;
        $impact = $impact instanceof IncidentImpact
            ? $impact
            : EnumValue::parse(IncidentImpact::class, $impact);

        if ($incident->status_page_id === null) {
            $pageId = $input['statusPageId'] ?? $input['status_page_id'] ?? null;

            if (! is_string($pageId) || $pageId === '') {
                throw new \InvalidArgumentException('A status page is required.');
            }

            StatusPage::query()->findOrFail($pageId);
            $incident->status_page_id = $pageId;
        }

        $incident->fill([
            'title' => $input['title'] ?? $incident->title,
            'status' => $status,
            'impact' => $impact,
            'started_at' => $input['startedAt'] ?? $input['started_at'] ?? $incident->started_at ?? now(),
        ]);

        if ($status->isResolved()) {
            $incident->resolved_at = $input['resolvedAt'] ?? $input['resolved_at'] ?? $incident->resolved_at ?? now();
        } elseif (array_key_exists('resolvedAt', $input) || array_key_exists('resolved_at', $input)) {
            $incident->resolved_at = $input['resolvedAt'] ?? $input['resolved_at'];
        } else {
            $incident->resolved_at = null;
        }

        $incident->save();

        if (array_key_exists('monitorIds', $input) || array_key_exists('monitor_ids', $input)) {
            $incident->monitors()->sync($input['monitorIds'] ?? $input['monitor_ids'] ?? []);
        }

        $message = $input['message'] ?? null;

        if ($isNew && is_string($message) && trim($message) !== '') {
            $incident->updates()->create([
                'status' => $incident->status,
                'message' => trim($message),
                'posted_at' => $incident->started_at ?? now(),
            ]);
        }

        return $incident->fresh(['updates', 'monitors', 'statusPage']) ?? $incident;
    }
}
