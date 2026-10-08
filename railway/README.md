# Nominal on Railway

Nominal with a public web process, a queue worker, the scheduler, Postgres, and a one-shot migrate service.

The app services use `ghcr.io/returnearly/nominal:latest`. That image needs the `railway/migrate.sh` script and `php artisan nominal:provision` from this repo, which land on `latest` after a push to `master`.

## Services

| Service | Role |
| --- | --- |
| web | Laravel Octane (FrankenPHP) on port 8080. Public domain. Health check `/up`. |
| worker | `php artisan queue:work` for `checks.$PROBE_REGION` and `default`. |
| scheduler | `php artisan schedule:work` (check dispatch, rollups, pruning). |
| migrate | Runs migrations, creates the default probe and first admin, then exits. |
| Postgres | Private Postgres 18. Password generated on deploy. Data on a volume. |

## Generated variables

Railway fills these in when the template is deployed:

- `APP_KEY` on **web**, as a Laravel `base64:` key. The other app services use `${{web.APP_KEY}}`.
- `NOMINAL_ADMIN_PASSWORD` on **web**. The other app services reference it.
- `POSTGRES_PASSWORD` on **Postgres**, and `DATABASE_URL` built from it.

`NOMINAL_ADMIN_EMAIL` defaults to `admin@nominal.test`. Change it on **web** before deploying. The migrate service follows that value.

`APP_URL` follows the web service's public domain. `DB_URL` follows the private Postgres URL. `TRUSTED_PROXIES=*` so Railway's proxy preserves HTTPS.

## After deploy

Open `https://<web-domain>/admin` and sign in with the admin email and `NOMINAL_ADMIN_PASSWORD` from the **web** service variables.

The migrate deployment exits after it succeeds. Redeploy **migrate** when you pull an image that adds migrations, then redeploy **web**, **worker**, and **scheduler**.

`PROBE_REGION` defaults to `local`. The provision command creates a default probe on queue `checks.local`, which is the queue the worker listens to. Change `PROBE_REGION` on **web** before the first deploy if this project should use another region, and keep the references on the other services.

ICMP from Railway usually cannot open raw sockets. Ping checks fall back to TCP.

## Publish

`template.json` is the Railway template config (`serializedConfig`). It is the definition to load in the template composer or to send as `serializedConfig` when deploying the template. After a project matches this file, create the draft with:

```bash
railway templates create --project <project> --environment production
railway templates publish <template-id> \
  --category Observability \
  --description "Nominal monitoring with web, worker, scheduler, Postgres, and migrations." \
  --readme-file railway/README.md
```
