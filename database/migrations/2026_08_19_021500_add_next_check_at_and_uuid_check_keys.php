<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('monitors', 'next_check_at')) {
            Schema::table('monitors', function (Blueprint $table): void {
                $table->timestamp('next_check_at')->nullable()->index();
            });

            DB::table('monitors')->orderBy('id')->each(function (object $monitor): void {
                $next = $monitor->last_checked_at
                    ? Carbon::parse($monitor->last_checked_at)->addSeconds((int) $monitor->interval_seconds)
                    : now();

                DB::table('monitors')->where('id', $monitor->id)->update([
                    'next_check_at' => $next,
                ]);
            });
        }

        $this->convertPrimaryKeyToUuid('check_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('monitor_id')->index();
            $table->uuid('probe_id')->index();
            $table->timestamp('checked_at')->index();
            $table->boolean('success');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('resolved_ip')->nullable();
            $table->timestamp('certificate_expires_at')->nullable();
            $table->text('message')->nullable();
            $table->json('condition_results')->nullable();
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
            $table->foreign('probe_id')->references('id')->on('probes')->cascadeOnDelete();
            $table->index(['monitor_id', 'checked_at']);
        });

        $this->convertPrimaryKeyToUuid('check_aggregates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('monitor_id');
            $table->uuid('probe_id')->nullable();
            $table->timestamp('period_start');
            $table->string('granularity');
            $table->unsignedInteger('up_count')->default(0);
            $table->unsignedInteger('down_count')->default(0);
            $table->unsignedInteger('avg_latency_ms')->nullable();
            $table->unique(['monitor_id', 'probe_id', 'period_start', 'granularity'], 'check_aggregates_unique');
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
        });
    }

    /**
     * @param  callable(Blueprint): void  $schema
     */
    private function convertPrimaryKeyToUuid(string $table, callable $schema): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $type = Schema::getColumnType($table, 'id');

        if (! in_array($type, ['integer', 'bigint', 'int'], true)) {
            return;
        }

        Schema::drop($table);
        Schema::create($table, $schema);
    }
};
