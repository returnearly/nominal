![Nominal](public/images/logo.png)

# Nominal

Database-backed endpoint monitoring. Gatus-shaped conditions, Filament admin, GraphQL mutations, Prometheus metrics, Reverb live events.

![Nominal — Modern self-hosted monitoring](public/images/og.jpg)

## Stack

- PHP 8.5, Laravel 13, Filament 5, Lighthouse GraphQL, Sanctum, Reverb
- Docker: `serversideup/php:8.5-frankenphp` with Laravel Octane, OPcache, and FrankenPHP worker mode
- Monitors: HTTP/HTTPS, GraphQL, ICMP ping, TCP, DNS, TLS, heartbeat, UDP, WebSocket, MySQL, Redis, and PostgreSQL
- Proxies: per-monitor HTTP/SOCKS URL for HTTP, GraphQL, TCP, TLS, WebSocket, and Redis; `HTTP_PROXY` / `ALL_PROXY` for HTTP checks and notification webhooks
- Conditions: `[STATUS]`, `[BODY]`, `[RESPONSE_TIME]`, `[IP]`, `[CONNECTED]`, `[CERTIFICATE_EXPIRATION]`, `[DNS_RCODE]`
- Notifications: mail, Slack, Teams, Discord webhook, generic webhook, PagerDuty
- Public status pages: multiple branded pages, custom domains, incidents, optional password
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

Create a GraphQL/Terraform token:

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

GraphQL monitors wrap `requestBody` as `{"query": "..."}` and default to POST:

```graphql
mutation {
  createMonitor(input: {
    name: "Countries"
    type: GraphQL
    target: "https://countries.trevorblades.com/"
    requestBody: "{ __typename }"
    conditions: ["[STATUS] == 200", "has([BODY].errors) == false"]
  }) { id }
}
```

Unauthenticated clients receive GraphQL `errors[]`. HTTP status is still 200 — Terraform maps those errors as failed applies.

Database monitors take a connection URL, log in, and run a version/status query (`SHOW TABLES` / public tables / Redis `INFO` and `DBSIZE`). Optional `requestBody` is custom SQL or a Redis command:

```graphql
mutation {
  createMonitor(input: {
    name: "Primary Postgres"
    type: Postgres
    target: "postgres://monitor:secret@db.example.com:5432/app"
    conditions: ["[CONNECTED] == true", "has([BODY].version) == true"]
  }) { id }
}
```

## Status pages

Nominal’s Filament UI is admin-only. Publish one or more public status pages (Uptime Kuma-style) instead of using the dashboard as the status page (Gatus-style).

Each page can:

- List a subset of monitors, with optional public names (targets hidden by default)
- Use a custom domain, logo, favicon, theme, footer, and CSS
- Optionally require a password
- Show incidents and scheduled maintenance with a public timeline

Path URL: `/status/{slug}`. Custom domain: CNAME the hostname to Nominal; the page is served at `/` on that host.

GraphQL: `createStatusPage`, `createIncident`, `addIncidentUpdate`.

## Reverb

Filament polls every 10s as a fallback. Live events are broadcast on:

- `private-monitors`
- `private-monitors.{uuid}`

Payloads: `MonitorStatusUpdated`, `CheckCompleted`. Authenticate `/broadcasting/auth` with the same Sanctum session or bearer token used for GraphQL.

Reverb through Cloudflare needs extra setup; GraphQL HTTP does not.

## Prometheus

`GET /metrics` — Redis/cache-backed counters and gauges, prefix `nominal_`. Labels: `monitor`, `type`, `success`, `region`.

## Badges

Public SVG and JSON badges for each monitor — the same first-class integration Gatus, Healthchecks, and Uptime Kuma expose. Served from `/embed` so they can be allowlisted independently of `/api` (e.g. through a firewall).

```
GET /embed/badges/{id}/status.svg
GET /embed/badges/{id}/status.json
GET /embed/badges/{id}/uptime/{period}/badge.svg
GET /embed/badges/{id}/uptime/{period}
GET /embed/badges/{id}/latency/{period}/badge.svg
GET /embed/badges/{id}/latency/{period}
```

Periods: `1h`, `24h`, `7d`, `30d` (any `{n}h` / `{n}d` up to 90 days). Omit the period to default to `24h`. JSON is [Shields.io endpoint](https://shields.io/badges/endpoint-badge) compatible (`schemaVersion`, `label`, `message`, `color`).

```md
![status](https://nominal.example/embed/badges/{id}/status.svg)
```

Copy URLs and markdown from the monitor page.

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

Production images (`linux/amd64` and `linux/arm64`) are published to the GitHub Container Registry on every push to `master` (`latest`) and on version tags:

```bash
docker pull ghcr.io/returnearly/nominal:latest
```

The package is public. If the first publish lands as private, set visibility to **Public** once under the repo's Packages settings.

## License

Copyright © 2026 Return Early.

Nominal is licensed under the [Elastic License 2.0](LICENSE) (source-available, not OSI open source). You may self-host it and run it for your own business, including for-profit internal use. You may not offer Nominal to third parties as a hosted or managed service.
