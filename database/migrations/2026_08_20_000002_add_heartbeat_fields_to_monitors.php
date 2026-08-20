<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->string('heartbeat_token', 64)->nullable()->unique()->after('dns_query_type');
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropUnique(['heartbeat_token']);
            $table->dropColumn(['heartbeat_token', 'last_heartbeat_at']);
        });
    }
};
