<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\BuildMonitorBadge;
use App\Actions\RenderShieldsBadge;
use App\Models\Monitor;
use App\Support\MonitorBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class BadgeController
{
    public function __construct(
        private BuildMonitorBadge $build,
        private RenderShieldsBadge $render,
    ) {}

    public function statusSvg(Monitor $monitor): Response
    {
        return $this->svg($this->build->handle($monitor, 'status'));
    }

    public function statusJson(Monitor $monitor): JsonResponse
    {
        return $this->json($this->build->handle($monitor, 'status'));
    }

    public function windowedSvg(Monitor $monitor, string $kind, ?string $period = null): Response
    {
        return $this->svg($this->badge($monitor, $kind, $period));
    }

    public function windowedJson(Monitor $monitor, string $kind, ?string $period = null): JsonResponse
    {
        return $this->json($this->badge($monitor, $kind, $period));
    }

    private function badge(Monitor $monitor, string $kind, ?string $period): MonitorBadge
    {
        try {
            return $this->build->handle($monitor, $kind, $period);
        } catch (InvalidArgumentException) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }
    }

    private function svg(MonitorBadge $badge): Response
    {
        return response($this->render->handle($badge->label, $badge->message, $badge->hexColor), 200, [
            ...$this->headers(),
            'Content-Type' => 'image/svg+xml; charset=utf-8',
        ]);
    }

    private function json(MonitorBadge $badge): JsonResponse
    {
        return response()->json($badge->toJson(), 200, $this->headers());
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Cache-Control' => 'public, max-age=60',
            'Access-Control-Allow-Origin' => '*',
        ];
    }
}
