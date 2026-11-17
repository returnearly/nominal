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
            $table->string('heartbeat_slug', 64)->nullable()->unique()->after('heartbeat_token');
            $table->boolean('heartbeat_slug_enabled')->default(false)->after('heartbeat_slug');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropUnique(['heartbeat_slug']);
            $table->dropColumn(['heartbeat_slug', 'heartbeat_slug_enabled']);
        });
    }
};
