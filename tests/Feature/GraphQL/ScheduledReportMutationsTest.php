<?php

declare(strict_types=1);

use App\Mail\ScheduledReportMail;
use App\Models\Monitor;
use App\Models\ScheduledReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

it('creates, updates, sends, and deletes a scheduled report', function () {
    $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));
    Mail::fake();

    $monitor = Monitor::factory()->create(['tags' => ['prod']]);

    $created = graphql('
        mutation ($input: CreateScheduledReportInput!) {
            createScheduledReport(input: $input) {
                id
                name
                type
                cadence
                send_time
                weekday
                scope
                recipients
                next_run_at
            }
        }
    ', [
        'input' => [
            'name' => 'Ops weekly',
            'recipients' => ['Ops@Example.com', 'lead@example.com'],
        ],
    ])->assertSuccessful()
        ->json('data.createScheduledReport');

    expect($created['type'])->toBe('Uptime')
        ->and($created['cadence'])->toBe('Weekly')
        ->and($created['send_time'])->toBe('08:00')
        ->and($created['weekday'])->toBe(1)
        ->and($created['scope'])->toBe('All')
        ->and($created['recipients'])->toBe(['ops@example.com', 'lead@example.com'])
        ->and($created['next_run_at'])->toBe('2026-10-12 08:00:00');

    $updated = graphql('
        mutation ($id: ID!, $input: UpdateScheduledReportInput!) {
            updateScheduledReport(id: $id, input: $input) {
                scope
                tags
                monitors { id }
            }
        }
    ', [
        'id' => $created['id'],
        'input' => [
            'scope' => 'Tags',
            'tags' => ['prod'],
        ],
    ])->assertSuccessful()
        ->json('data.updateScheduledReport');

    expect($updated['scope'])->toBe('Tags')
        ->and($updated['tags'])->toBe(['prod'])
        ->and($updated['monitors'])->toBe([]);

    $next = ScheduledReport::query()->findOrFail($created['id'])->next_run_at?->toDateTimeString();

    $sent = graphql('
        mutation ($id: ID!) {
            sendScheduledReport(id: $id) {
                last_sent_at
                last_error
                next_run_at
            }
        }
    ', ['id' => $created['id']])->assertSuccessful()
        ->json('data.sendScheduledReport');

    expect($sent['last_error'])->toBeNull()
        ->and($sent['last_sent_at'])->not->toBeNull()
        ->and($sent['next_run_at'])->toBe($next);

    Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $mail) use ($monitor): bool {
        $html = $mail->render();

        return $mail->hasTo('ops@example.com')
            && $mail->hasTo('lead@example.com')
            && str_contains($html, $monitor->name);
    });

    $deleted = graphql('
        mutation ($id: ID!) {
            deleteScheduledReport(id: $id)
        }
    ', ['id' => $created['id']])->assertSuccessful()
        ->json('data.deleteScheduledReport');

    expect($deleted)->toBeTrue()
        ->and(ScheduledReport::query()->find($created['id']))->toBeNull();
});
