<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->unsignedInteger('minimum_open_seconds')->default(0)->after('config');
        });

        Schema::table('monitor_notification_channel', function (Blueprint $table) {
            $table->timestamp('triggered_at')->nullable()->after('triggered');
        });
    }

    public function down(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropColumn('minimum_open_seconds');
        });

        Schema::table('monitor_notification_channel', function (Blueprint $table) {
            $table->dropColumn('triggered_at');
        });
    }
};
