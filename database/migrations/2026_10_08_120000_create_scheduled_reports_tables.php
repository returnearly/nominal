<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('type');
            $table->string('cadence');
            $table->string('send_time');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->string('scope');
            $table->json('tags')->nullable();
            $table->json('recipients');
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduled_report_monitor', function (Blueprint $table) {
            $table->uuid('scheduled_report_id');
            $table->uuid('monitor_id');
            $table->primary(['scheduled_report_id', 'monitor_id']);
            $table->foreign('scheduled_report_id')->references('id')->on('scheduled_reports')->cascadeOnDelete();
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_report_monitor');
        Schema::dropIfExists('scheduled_reports');
    }
};
