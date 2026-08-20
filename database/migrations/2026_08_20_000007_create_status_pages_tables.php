<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('footer_text')->nullable();
            $table->text('custom_css')->nullable();
            $table->string('theme')->default('dark');
            $table->boolean('published')->default(false);
            $table->boolean('show_targets')->default(false);
            $table->string('password')->nullable();
            $table->unsignedInteger('refresh_seconds')->default(30);
            $table->timestamps();
        });

        Schema::create('status_page_monitor', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('status_page_id');
            $table->uuid('monitor_id');
            $table->string('public_name')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['status_page_id', 'monitor_id']);
            $table->foreign('status_page_id')->references('id')->on('status_pages')->cascadeOnDelete();
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('status_page_id')->index();
            $table->string('title');
            $table->string('status');
            $table->string('impact');
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->foreign('status_page_id')->references('id')->on('status_pages')->cascadeOnDelete();
        });

        Schema::create('incident_updates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('incident_id')->index();
            $table->string('status');
            $table->text('message');
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
        });

        Schema::create('incident_monitor', function (Blueprint $table) {
            $table->uuid('incident_id');
            $table->uuid('monitor_id');
            $table->primary(['incident_id', 'monitor_id']);
            $table->foreign('incident_id')->references('id')->on('incidents')->cascadeOnDelete();
            $table->foreign('monitor_id')->references('id')->on('monitors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_monitor');
        Schema::dropIfExists('incident_updates');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('status_page_monitor');
        Schema::dropIfExists('status_pages');
    }
};
