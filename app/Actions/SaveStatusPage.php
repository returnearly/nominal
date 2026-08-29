<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StatusPageTheme;
use App\Models\StatusPage;
use App\Support\EnumValue;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class SaveStatusPage implements ActionsPatternInterface
{
    use ActionsPattern;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input, ?StatusPage $page = null): StatusPage
    {
        $page ??= new StatusPage;

        $theme = $input['theme'] ?? $page->theme ?? StatusPageTheme::Dark;

        $page->fill([
            'name' => $input['name'] ?? $page->name,
            'slug' => $input['slug'] ?? $page->slug,
            'custom_domain' => array_key_exists('customDomain', $input) || array_key_exists('custom_domain', $input)
                ? StatusPage::normalizeDomain($input['customDomain'] ?? $input['custom_domain'] ?? null)
                : $page->custom_domain,
            'headline' => $input['headline'] ?? $page->headline,
            'description' => $input['description'] ?? $page->description,
            'logo_url' => $input['logoUrl'] ?? $input['logo_url'] ?? $page->logo_url,
            'favicon_url' => $input['faviconUrl'] ?? $input['favicon_url'] ?? $page->favicon_url,
            'footer_text' => $input['footerText'] ?? $input['footer_text'] ?? $page->footer_text,
            'custom_css' => $input['customCss'] ?? $input['custom_css'] ?? $page->custom_css,
            'theme' => $theme instanceof StatusPageTheme
                ? $theme
                : EnumValue::parse(StatusPageTheme::class, $theme),
            'published' => $input['published'] ?? $page->published ?? false,
            'show_targets' => $input['showTargets'] ?? $input['show_targets'] ?? $page->show_targets ?? false,
            'refresh_seconds' => $input['refreshSeconds'] ?? $input['refresh_seconds'] ?? $page->refresh_seconds ?? 30,
        ]);

        if (array_key_exists('password', $input)) {
            $page->password = filled($input['password']) ? $input['password'] : null;
        }

        $page->save();

        if (array_key_exists('monitors', $input)) {
            $this->syncMonitors($page, $input['monitors'] ?? []);
        } elseif (array_key_exists('monitorIds', $input) || array_key_exists('monitor_ids', $input)) {
            $this->syncMonitors($page, $input['monitorIds'] ?? $input['monitor_ids'] ?? []);
        }

        return $page->fresh(['listings.monitor', 'monitors', 'incidents']) ?? $page;
    }

    /**
     * @param  list<string>|list<array{id?: string, monitorId?: string, publicName?: string|null}>  $monitors
     */
    private function syncMonitors(StatusPage $page, array $monitors): void
    {
        $page->listings()->delete();

        foreach (array_values($monitors) as $sort => $monitor) {
            if (is_string($monitor)) {
                $page->listings()->create([
                    'monitor_id' => $monitor,
                    'sort' => $sort,
                ]);

                continue;
            }

            $id = $monitor['monitorId'] ?? $monitor['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $page->listings()->create([
                'monitor_id' => $id,
                'public_name' => $monitor['publicName'] ?? $monitor['public_name'] ?? null,
                'sort' => $sort,
            ]);
        }
    }
}
