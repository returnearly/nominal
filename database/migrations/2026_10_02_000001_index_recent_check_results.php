<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('check_results', ['monitor_id', 'checked_at', 'id'])) {
            Schema::table('check_results', function (Blueprint $table): void {
                $table->index(['monitor_id', 'checked_at', 'id']);
            });
        }

        if (Schema::hasIndex('check_results', ['monitor_id', 'checked_at'])) {
            Schema::table('check_results', function (Blueprint $table): void {
                $table->dropIndex(['monitor_id', 'checked_at']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('check_results', ['monitor_id', 'checked_at'])) {
            Schema::table('check_results', function (Blueprint $table): void {
                $table->index(['monitor_id', 'checked_at']);
            });
        }

        if (Schema::hasIndex('check_results', ['monitor_id', 'checked_at', 'id'])) {
            Schema::table('check_results', function (Blueprint $table): void {
                $table->dropIndex(['monitor_id', 'checked_at', 'id']);
            });
        }
    }
};
