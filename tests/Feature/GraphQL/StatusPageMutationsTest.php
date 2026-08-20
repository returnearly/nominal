<?php

declare(strict_types=1);

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;

it('creates, updates, and deletes a status page', function () {
    $monitor = Monitor::factory()->create(['name' => 'Payments API']);

    $created = graphql('
        mutation ($input: CreateStatusPageInput!) {
            createStatusPage(input: $input) {
                id
                name
                slug
                published
                show_targets
                passwordProtected
                theme
                publicUrl
                listings {
                    public_name
                    monitor { name }
                }
            }
        }
    ', [
        'input' => [
            'name' => 'Acme Status',
            'slug' => 'acme',
            'theme' => 'Dark',
            'published' => true,
            'monitors' => [
                ['monitorId' => $monitor->id, 'publicName' => 'API'],
            ],
        ],
    ])->assertSuccessful()
        ->json('data.createStatusPage');

    expect($created['name'])->toBe('Acme Status')
        ->and($created['slug'])->toBe('acme')
        ->and($created['published'])->toBeTrue()
        ->and($created['show_targets'])->toBeFalse()
        ->and($created['passwordProtected'])->toBeFalse()
        ->and($created['theme'])->toBe('Dark')
        ->and($created['listings'])->toHaveCount(1)
        ->and($created['listings'][0]['public_name'])->toBe('API')
        ->and($created['listings'][0]['monitor']['name'])->toBe('Payments API');

    $updated = graphql('
        mutation ($id: ID!, $input: UpdateStatusPageInput!) {
            updateStatusPage(id: $id, input: $input) {
                headline
                custom_domain
                passwordProtected
                monitors { id }
            }
        }
    ', [
        'id' => $created['id'],
        'input' => [
            'headline' => 'Customer services',
            'customDomain' => 'https://status.acme.test',
            'password' => 'secret',
            'monitorIds' => [],
        ],
    ])->assertSuccessful()
        ->json('data.updateStatusPage');

    expect($updated['headline'])->toBe('Customer services')
        ->and($updated['custom_domain'])->toBe('status.acme.test')
        ->and($updated['passwordProtected'])->toBeTrue()
        ->and($updated['monitors'])->toBe([]);

    $listed = graphql('{ statusPages { slug } }')->json('data.statusPages');

    expect($listed)->toHaveCount(1)
        ->and($listed[0]['slug'])->toBe('acme');

    $deleted = graphql('
        mutation ($id: ID!) {
            deleteStatusPage(id: $id)
        }
    ', ['id' => $created['id']])->json('data.deleteStatusPage');

    expect($deleted)->toBeTrue()
        ->and(StatusPage::query()->count())->toBe(0);
});

it('creates an incident and posts updates', function () {
    $page = StatusPage::factory()->create();
    $monitor = Monitor::factory()->create();
    $page->listings()->create(['monitor_id' => $monitor->id, 'sort' => 0]);

    $created = graphql('
        mutation ($input: CreateIncidentInput!) {
            createIncident(input: $input) {
                id
                title
                status
                impact
                updates { message status }
                monitors { id }
            }
        }
    ', [
        'input' => [
            'statusPageId' => $page->id,
            'title' => 'API latency',
            'status' => 'Investigating',
            'impact' => 'Minor',
            'message' => 'We are investigating elevated latency.',
            'monitorIds' => [$monitor->id],
        ],
    ])->assertSuccessful()
        ->json('data.createIncident');

    expect($created['title'])->toBe('API latency')
        ->and($created['status'])->toBe('Investigating')
        ->and($created['updates'][0]['message'])->toBe('We are investigating elevated latency.')
        ->and($created['monitors'][0]['id'])->toBe($monitor->id);

    $resolved = graphql('
        mutation ($incidentId: ID!, $input: AddIncidentUpdateInput!) {
            addIncidentUpdate(incidentId: $incidentId, input: $input) {
                status
                resolved_at
                updates { status message }
            }
        }
    ', [
        'incidentId' => $created['id'],
        'input' => [
            'status' => 'Resolved',
            'message' => 'Latency has returned to normal.',
        ],
    ])->assertSuccessful()
        ->json('data.addIncidentUpdate');

    expect($resolved['status'])->toBe('Resolved')
        ->and($resolved['resolved_at'])->not->toBeNull()
        ->and($resolved['updates'])->toHaveCount(2);

    $deleted = graphql('
        mutation ($id: ID!) {
            deleteIncident(id: $id)
        }
    ', ['id' => $created['id']])->json('data.deleteIncident');

    expect($deleted)->toBeTrue();
});

it('does not expose status page passwords over GraphQL', function () {
    $user = User::factory()->create();
    StatusPage::factory()->passwordProtected('secret')->create(['slug' => 'private']);

    $response = graphql('{ statusPages { slug passwordProtected } }', [], $user);

    expect($response->json('data.statusPages.0.passwordProtected'))->toBeTrue();

    $invalid = graphql('{ statusPages { password } }', [], $user);

    expect($invalid->json('errors.0.message'))->toContain('password');
});
