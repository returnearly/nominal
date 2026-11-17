<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('monitors', 'heartbeat_slug_enabled')) {
            return;
        }

        DB::table('monitors')->whereNull('heartbeat_slug_enabled')->update([
            'heartbeat_slug_enabled' => false,
        ]);

        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('heartbeat_slug_enabled')->default(false)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('monitors', 'heartbeat_slug_enabled')) {
            return;
        }

        Schema::table('monitors', function (Blueprint $table) {
            $table->boolean('heartbeat_slug_enabled')->default(false)->nullable()->change();
        });
    }
};
