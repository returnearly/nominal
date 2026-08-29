# Nominal

Database-backed endpoint monitoring. Gatus-shaped conditions, Filament admin, GraphQL mutations, Prometheus metrics, Reverb live events.

Aviation sense of the word: all systems nominal.

## Stack

- PHP 8.5, Laravel 13, Filament 5, Lighthouse GraphQL, Sanctum, Reverb
- Docker: `serversideup/php:8.5-frankenphp` with Laravel Octane, OPcache, and FrankenPHP worker mode
- Monitors: HTTP/HTTPS (custom method, headers, body) and ICMP ping (TCP fallback)
- Conditions: `[STATUS]`, `[BODY]`, `[RESPONSE_TIME]`, `[IP]`, `[CONNECTED]`, `[CERTIFICATE_EXPIRATION]`
- Notifications: mail, Slack, Teams, Discord webhook, generic webhook, PagerDuty
- Terraform provider: [`returnearly/terraform-provider-nominal`](https://github.com/returnearly/terraform-provider-nominal)

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Admin: [http://localhost:8000/admin](http://localhost:8000/admin)

Notification channels, probes, users, and API tokens live under **Settings**.

Create a GraphQL/Terraform token from Settings → API Tokens, or:

```bash
php artisan nominal:token admin@nominal.test
```

Run checks locally:

```bash
php artisan nominal:dispatch-due-checks
php artisan queue:work --queue=checks.local,default
```

## Interface auth

`INTERFACE_AUTH` controls the Filament admin only. GraphQL always uses Sanctum tokens.

| Value | Behavior |
| --- | --- |
| `login` | Email and password (default). Seeded: `admin@nominal.test` / `password` |
| `none` | No login page. Visitors are signed in as `operator@nominal.local` (created on first visit). |
| `cloudflare` | [Cloudflare Access](https://developers.cloudflare.com/cloudflare-one/policies/access/) via [`returnearly/laravel-cloudflare-zero-trust`](https://github.com/returnearly/laravel-cloudflare-zero-trust). SSO users are created on first request. |

Cloudflare Access:

```env
INTERFACE_AUTH=cloudflare
CLOUDFLARE_ZERO_TRUST_ENABLED=true
CLOUDFLARE_TEAM_DOMAIN=https://returnearly.cloudflareaccess.com
CLOUDFLARE_ADMIN_AUD=your-application-aud-tag
```

The origin must sit behind Cloudflare Tunnel (or equivalent). A valid Access JWT is a bearer credential.

## GraphQL

`POST /graphql` with a Sanctum bearer token (`php artisan nominal:token you@example.com`).

```graphql
mutation {
  createMonitor(input: {
    name: "API"
    type: Http
    target: "https://example.com/health"
    conditions: ["[STATUS] == 200"]
  }) { id }
}
```

Unauthenticated clients receive GraphQL `errors[]`. HTTP status is still 200 — Terraform maps those errors as failed applies.

## Reverb

Filament polls every 10s as a fallback. Live events are broadcast on:

- `private-monitors`
- `private-monitors.{uuid}`

Payloads: `MonitorStatusUpdated`, `CheckCompleted`. Authenticate `/broadcasting/auth` with the same Sanctum session or bearer token used for GraphQL.

Reverb through Cloudflare needs extra setup; GraphQL HTTP does not.

## Prometheus

`GET /metrics` — Redis/cache-backed counters and gauges, prefix `nominal_`. Labels: `monitor`, `group`, `type`, `success`, `region`.

## Multi-region workers

Each probe has a queue such as `checks.us-east`. Workers set `PROBE_REGION=us-east` and listen to `checks.us-east`. SQLite is single-node only; use MySQL or Postgres for multiple writers.

ICMP inside Docker needs `cap_add: [NET_RAW]`. Ping falls back to TCP 443/80 when ICMP is blocked.

## Docker

App container is `serversideup/php:8.5-frankenphp` running Laravel Octane (`octane:start --server=frankenphp`) with OPcache on. Compose (development) adds `--watch` plus `opcache.validate_timestamps` so PHP/config changes reload without rebuilding. Production image leaves timestamps off and omits `--watch`.

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
composer install
docker compose up --build
```

App is on [http://localhost:8000](http://localhost:8000) (container port 8080). Queue, scheduler, and Reverb are the same image with `command:` overrides. Extra regional workers copy `worker` and change `PROBE_REGION` / `--queue`.
