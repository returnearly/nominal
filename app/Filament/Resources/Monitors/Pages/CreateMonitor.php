<?php

declare(strict_types=1);

namespace App\Filament\Resources\Monitors\Pages;

use App\Conditions\ConditionExpression;
use App\Enums\IpFamily;
use App\Enums\MonitorType;
use App\Filament\Resources\Monitors\MonitorFormState;
use App\Filament\Resources\Monitors\MonitorResource;
use App\Models\Monitor;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;

final class CreateMonitor extends CreateRecord
{
    protected static string $resource = MonitorResource::class;

    #[Url]
    public ?string $replicate = null;

    protected function afterFill(): void
    {
        $source = $this->sourceMonitor();

        if (! $source instanceof Monitor) {
            return;
        }

        $state = MonitorFormState::from($source);
        $conditions = $state['conditions'];
        unset($state['conditions']);

        $this->form->fill($state);

        // Relationship repeaters ignore fill() until a record exists.
        $this->data['conditions'] = $conditions;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['type'] ?? null;
        $isHeartbeat = $type === MonitorType::Heartbeat || $type === MonitorType::Heartbeat->value;

        if (! $isHeartbeat) {
            return $data;
        }

        if (blank($data['target'] ?? null)) {
            $data['target'] = 'heartbeat';
        }

        $data['ip_family'] = IpFamily::Any;
        $data['timeout_seconds'] = $data['timeout_seconds'] ?? 10;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->type->isHeartbeat()) {
            $this->record->conditions()->delete();
            $this->record->probes()->sync([]);

            return;
        }

        if ($this->record->conditions()->doesntExist()) {
            foreach (ConditionExpression::defaultExpressions($this->record->type) as $sort => $expression) {
                $this->record->conditions()->create([
                    'expression' => $expression,
                    'sort' => $sort,
                ]);
            }
        }
    }

    private function sourceMonitor(): ?Monitor
    {
        $id = $this->replicate;

        if (! is_string($id) || $id === '') {
            $id = request()->query('replicate');
        }

        if (! is_string($id) || $id === '') {
            return null;
        }

        return Monitor::query()
            ->with(['conditions', 'probes', 'notificationChannels'])
            ->find($id);
    }
}
