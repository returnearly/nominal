<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ResolvePublicStatusPage;
use App\Models\Incident;
use App\Models\StatusPage;
use App\Support\StatusPageSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class StatusPageController
{
    public function home(Request $request): RedirectResponse|Response|View
    {
        $page = ResolvePublicStatusPage::make()->handle(host: $request->getHost());

        if ($page instanceof StatusPage) {
            return $this->show($request, $page, onCustomDomain: true);
        }

        return redirect('/admin');
    }

    public function showBySlug(Request $request, string $slug): Response|View
    {
        return $this->show($request, $this->publishedBySlug($slug));
    }

    public function incidentBySlug(Request $request, string $slug, string $incident): Response|View
    {
        return $this->incident($request, $this->publishedBySlug($slug), $incident);
    }

    public function unlockBySlug(Request $request, string $slug): RedirectResponse|Response|View
    {
        return $this->unlock($request, $this->publishedBySlug($slug), '/status/'.$slug);
    }

    public function incidentOnDomain(Request $request, string $incident): Response|View
    {
        return $this->incident($request, $this->publishedByHost($request), $incident, onCustomDomain: true);
    }

    public function unlockOnDomain(Request $request): RedirectResponse|Response|View
    {
        return $this->unlock($request, $this->publishedByHost($request), '/');
    }

    private function show(Request $request, StatusPage $page, bool $onCustomDomain = false): Response|View
    {
        if ($gate = $this->passwordGate($request, $page, $onCustomDomain)) {
            return $gate;
        }

        return response()->view('status.show', [
            'snapshot' => StatusPageSnapshot::for($page, $onCustomDomain),
        ]);
    }

    private function incident(Request $request, StatusPage $page, string $incidentId, bool $onCustomDomain = false): Response|View
    {
        if ($gate = $this->passwordGate($request, $page, $onCustomDomain)) {
            return $gate;
        }

        $incident = Incident::query()
            ->where('status_page_id', $page->id)
            ->with(['updates', 'monitors'])
            ->findOrFail($incidentId);

        return response()->view('status.incident', [
            'page' => $page,
            'incident' => $incident,
            'onCustomDomain' => $onCustomDomain,
        ]);
    }

    private function unlock(Request $request, StatusPage $page, string $redirectTo): RedirectResponse|Response|View
    {
        abort_unless($page->isPasswordProtected(), 404);

        $valid = Hash::check((string) $request->input('password'), (string) $page->password);

        if (! $valid) {
            return response()
                ->view('status.password', [
                    'page' => $page,
                    'error' => 'That password is incorrect.',
                    'unlockAction' => $request->url(),
                ], 422);
        }

        $request->session()->put($page->passwordSessionKey(), true);

        return redirect($redirectTo);
    }

    private function passwordGate(Request $request, StatusPage $page, bool $onCustomDomain): ?View
    {
        if (! $page->isPasswordProtected()) {
            return null;
        }

        if ($request->session()->get($page->passwordSessionKey()) === true) {
            return null;
        }

        return view('status.password', [
            'page' => $page,
            'error' => null,
            'unlockAction' => $onCustomDomain ? url('/unlock') : url('/status/'.$page->slug.'/unlock'),
        ]);
    }

    private function publishedBySlug(string $slug): StatusPage
    {
        $page = ResolvePublicStatusPage::make()->handle(slug: $slug);

        abort_if($page === null, 404);

        return $page;
    }

    private function publishedByHost(Request $request): StatusPage
    {
        $page = ResolvePublicStatusPage::make()->handle(host: $request->getHost());

        abort_if($page === null, 404);

        return $page;
    }
}
