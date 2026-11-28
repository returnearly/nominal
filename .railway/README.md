# Railway

`.railway/railway.ts` describes a Nominal project:

- **web** serves the admin, GraphQL, and status pages on port 8080
- **worker** runs checks from the `checks.local` queue
- **scheduler** dispatches due checks, rollups, and reports
- **reverb** serves live admin events
- **Postgres** is the shared database
- **Redis** is the cache

Install the Railway SDK, then preview and apply against a linked project:

```bash
npm install
railway login
railway init --name nominal
railway config plan
railway config apply
```

`railway config plan` only reads the linked project. Apply when the plan matches the stack you want. Railway clones `returnearly/nominal` at `master` for the image build.

The web service creates `admin@nominal.local` the first time it boots and prints the password in its logs. Later boots leave that login alone. The worker listens to `checks.${PROBE_REGION}`; the seeded probe uses `checks.local`.
