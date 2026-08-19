<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('probes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('queue');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('monitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('group')->nullable();
            $table->string('type');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('interval_seconds')->default(60);
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->string('ip_family')->default('any');
            $table->string('target');
            $table->string('method')->nullable();
            $table->text('request_headers')->nullable();
            $table->text('request_body')->nullable();
            $table->boolean('follow_redirects')->default(true);
            $table->boolean('verify_tls')->default(true);
            $table->string('status')->default('pending');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_status_changed_at')->nullable();
            $table->unsignedInteger('consecutive_successes')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('retention_days')->default(30);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('monitor_probe', function (Blueprint $table) {
            $table->uuid('monitor_id');
            $table->uuid('probe_id');
            $table->primary(['monitor_id', 'probe_id']);
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
            $table->foreign('probe_id')->references('id')->on('probes')->cascadeOnDelete();
        });

        Schema::create('monitor_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('monitor_id');
            $table->string('expression');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
        });

        Schema::create('check_results', function (Blueprint $table) {
            $table->id();
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

        Schema::create('check_aggregates', function (Blueprint $table) {
            $table->id();
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

        Schema::create('notification_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->text('config');
            $table->timestamps();
        });

        Schema::create('monitor_notification_channel', function (Blueprint $table) {
            $table->uuid('monitor_id');
            $table->uuid('notification_channel_id');
            $table->unsignedInteger('failure_threshold')->default(3);
            $table->unsignedInteger('success_threshold')->default(2);
            $table->boolean('send_on_resolved')->default(true);
            $table->unsignedInteger('reminder_interval_seconds')->nullable();
            $table->boolean('triggered')->default(false);
            $table->timestamp('last_notified_at')->nullable();
            $table->primary(['monitor_id', 'notification_channel_id'], 'monitor_notification_channel_primary');
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
            $table->foreign('notification_channel_id')->references('id')->on('notification_channels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_notification_channel');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('check_aggregates');
        Schema::dropIfExists('check_results');
        Schema::dropIfExists('monitor_conditions');
        Schema::dropIfExists('monitor_probe');
        Schema::dropIfExists('monitors');
        Schema::dropIfExists('probes');
    }
};
