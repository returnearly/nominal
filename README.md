# Nominal

Database-backed endpoint monitoring. Gatus-shaped conditions, Filament admin, GraphQL mutations, Prometheus metrics, Reverb live events.

Aviation sense of the word: all systems nominal.

## Stack

- Laravel 13, Filament 5, Lighthouse GraphQL, Sanctum, Reverb
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
Seeded login: `admin@nominal.test` / `password`

Create a GraphQL/Terraform token:

```bash
php artisan nominal:token admin@nominal.test
```

Run checks locally:

```bash
php artisan nominal:dispatch-due-checks
php artisan queue:work --queue=checks.local,default
```

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

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
docker compose up --build
```

`CONTAINER_ROLE` is `app`, `worker`, `scheduler`, or `reverb`. Extra regional workers are copies of `worker` with a different `PROBE_REGION`.
