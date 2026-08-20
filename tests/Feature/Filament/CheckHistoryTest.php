<?php

declare(strict_types=1);

use App\Filament\Resources\CheckResults\Pages\ListCheckResults;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Probe;
use App\Models\User;
use Filament\Tables\Enums\PaginationMode;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Livewire;

it('shows check history for all monitors', function () {
    $user = User::factory()->create();
    $probe = Probe::factory()->create(['name' => 'Local']);
    $http = Monitor::factory()->create(['name' => 'Payments API']);
    $ping = Monitor::factory()->ping()->create(['name' => 'Edge ping']);

    CheckResult::factory()->create([
        'monitor_id' => $http->id,
        'probe_id' => $probe->id,
        'http_status' => 200,
    ]);
    CheckResult::factory()->create([
        'monitor_id' => $ping->id,
        'probe_id' => $probe->id,
        'success' => false,
        'http_status' => null,
        'message' => 'timed out',
    ]);

    $this->actingAs($user)
        ->get('/admin/history')
        ->assertOk()
        ->assertSee('Payments API')
        ->assertSee('Edge ping')
        ->assertSee('timed out')
        ->assertSee('Latency')
        ->assertDontSee('Latency ms');
});

it('paginates history without counting rows', function () {
    $user = User::factory()->create();
    CheckResult::factory()->create();

    $livewire = Livewire::actingAs($user)->test(ListCheckResults::class);
    $table = $livewire->instance()->getTable();
    $records = $livewire->instance()->getTableRecords();

    expect($table->getPaginationMode())->toBe(PaginationMode::Simple)
        ->and($table->getPaginationPageOptions())->toBe([50, 100, 250])
        ->and($table->getDefaultPaginationPageOption())->toBe(50)
        ->and($records)->toBeInstanceOf(Paginator::class)
        ->and($records)->not->toBeInstanceOf(LengthAwarePaginator::class);
});
