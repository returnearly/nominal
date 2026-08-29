<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->dropForeign(['probe_id']);
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->uuid('probe_id')->nullable()->change();
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->foreign('probe_id')->references('id')->on('probes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->dropForeign(['probe_id']);
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->uuid('probe_id')->nullable(false)->change();
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->foreign('probe_id')->references('id')->on('probes')->cascadeOnDelete();
        });
    }
};
