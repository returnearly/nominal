<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->indexIfMissing('monitors', ['enabled', 'next_check_at']);
        $this->indexIfMissing('monitor_conditions', ['monitor_id', 'sort']);
        $this->indexIfMissing('monitor_probe', ['probe_id']);
        $this->indexIfMissing('monitor_notification_channel', ['notification_channel_id']);
        $this->indexIfMissing('maintenance_window_monitor', ['monitor_id']);
        $this->indexIfMissing('status_page_monitor', ['monitor_id']);
        $this->indexIfMissing('incident_monitor', ['monitor_id']);
        $this->indexIfMissing('incidents', ['status_page_id', 'started_at']);
        $this->indexIfMissing('incident_updates', ['incident_id', 'posted_at']);
    }

    public function down(): void
    {
        $this->dropNamedIndex('monitors', 'monitors_enabled_next_check_at_index');
        $this->dropNamedIndex('monitor_conditions', 'monitor_conditions_monitor_id_sort_index');
        $this->dropNamedIndex('monitor_probe', 'monitor_probe_probe_id_index');
        $this->dropNamedIndex('monitor_notification_channel', 'monitor_notification_channel_notification_channel_id_index');
        $this->dropNamedIndex('maintenance_window_monitor', 'maintenance_window_monitor_monitor_id_index');
        $this->dropNamedIndex('status_page_monitor', 'status_page_monitor_monitor_id_index');
        $this->dropNamedIndex('incident_monitor', 'incident_monitor_monitor_id_index');
        $this->dropNamedIndex('incidents', 'incidents_status_page_id_started_at_index');
        $this->dropNamedIndex('incident_updates', 'incident_updates_incident_id_posted_at_index');
    }

    /**
     * @param  list<string>  $columns
     */
    private function indexIfMissing(string $table, array $columns): void
    {
        if (Schema::hasIndex($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
            $blueprint->index($columns);
        });
    }

    private function dropNamedIndex(string $table, string $name): void
    {
        if (! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name): void {
            $blueprint->dropIndex($name);
        });
    }
};
