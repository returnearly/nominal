<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\StatusPage;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class ResolvePublicStatusPage implements ActionsPatternInterface
{
    use ActionsPattern;

    private function forSlug(string $slug): ?StatusPage
    {
        return StatusPage::query()
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    private function forHost(string $host): ?StatusPage
    {
        $domain = StatusPage::normalizeDomain($host);

        if ($domain === null || StatusPage::hostMatchesApp($host)) {
            return null;
        }

        $page = StatusPage::query()
            ->where('custom_domain', $domain)
            ->first();

        if ($page === null) {
            return null;
        }

        abort_unless($page->published, 404);

        return $page;
    }

    public function handle(?string $slug = null, ?string $host = null): ?StatusPage
    {
        if (filled($slug)) {
            return $this->forSlug((string) $slug);
        }

        if (filled($host)) {
            return $this->forHost((string) $host);
        }

        return null;
    }
}
