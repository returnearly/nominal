<?php

declare(strict_types=1);

use App\Support\MonitorTags;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('monitors')->orderBy('id')->get() as $monitor) {
            $group = trim((string) ($monitor->group ?? ''));

            if ($group === '') {
                continue;
            }

            $existing = $monitor->tags;

            if (is_string($existing) && $existing !== '') {
                $existing = json_decode($existing, true);
            }

            DB::table('monitors')->where('id', $monitor->id)->update([
                'tags' => json_encode(MonitorTags::normalize(array_merge(
                    is_array($existing) ? $existing : [],
                    [$group],
                ))),
            ]);
        }

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('group');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->string('group')->nullable()->after('name');
        });
    }
};
