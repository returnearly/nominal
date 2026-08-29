<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('message')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('applies_to_all')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_window_monitor', function (Blueprint $table) {
            $table->uuid('maintenance_window_id');
            $table->uuid('monitor_id');
            $table->primary(['maintenance_window_id', 'monitor_id']);
            $table->foreign('maintenance_window_id')->references('id')->on('maintenance_windows')->cascadeOnDelete();
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_window_monitor');
        Schema::dropIfExists('maintenance_windows');
    }
};
