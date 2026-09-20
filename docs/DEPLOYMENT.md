# Deployment Plan

For the current two-day demo, see [demo delivery](demo/DELIVERY.md). The environments below describe the later deployment direction, not resources already configured.

## Verified connection status

On 17/09/2026 the user-authorized password was saved only to the ignored `mofi-app/.env`. A read-only PDO PostgreSQL connection using `sslmode=require` succeeded and returned zero tables in `public`. No schema changes were performed. Laravel application-level integration and Supabase Data API/grants still need checking before implementation exposes any tables. Credentials are intentionally absent from this document.

## Environments

- Local: developer machine with a local `.env` and Supabase development project.
- Staging: separate Supabase project, seeded test data, and preview deployment.
- Production: protected secrets, backups, monitoring, queue worker, and scheduler.

## Recommended services

- GitHub: source repository and CI.
- Supabase: PostgreSQL, optional storage, and database dashboard.
- Railway, Render, or Laravel Cloud: Laravel application and worker.
- Sentry: error monitoring.

## Demo deployment decision (2026-09-20)

- The current application is Laravel + React/Inertia in one deployable monolith. For the demo, deploy the whole `mofi-app` directory to Railway and keep PostgreSQL on the existing Supabase project.
- Vercel is not used for this build because splitting the Inertia page server from the Laravel session/auth/API would require a separate frontend architecture and cross-origin cookie configuration.
- `mofi-app/railway.json` builds the Vite bundle, runs migrations and idempotent demo/admin seeders before deploy, serves Laravel on Railway's `$PORT`, and exposes `/up` as the health check.
- Required Railway variables are listed below. Values must be entered in Railway's Variables screen or CLI and must never be committed.

```text
APP_ENV=production
APP_DEBUG=false
APP_KEY=<existing application key>
APP_URL=<Railway public domain>
LOG_CHANNEL=stderr
LOG_LEVEL=error
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-2.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.egqpjrwmnckgzlajemnl
DB_PASSWORD=<Supabase database password>
DB_SSLMODE=require
DB_EMULATE_PREPARES=true
DEMO_ENABLED=true
DEMO_LOGIN_PASSWORD=<local demo password>
DEMO_ADMIN_PASSWORD=<local admin password>
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
```

After the first deploy, verify `/up`, `/`, `/login`, demo login, `/market`, `/transactions`, and `/admin`. Treat the hosted instance as a demo environment: it uses simulated financial data and must not receive real credentials, broker keys, payment details, or production personal data.

For Railway, use the Supabase Session Pooler host and project-qualified username above. The direct database host resolves to IPv6 on this project and is not reachable from the Railway runtime; local development can continue using the direct TLS connection documented in the local setup notes.

## Current Supabase project

- Project: `mofi-db`
- Project ref: `egqpjrwmnckgzlajemnl`
- Region: Asia-Pacific (Sydney)
- Database host: `db.egqpjrwmnckgzlajemnl.supabase.co`

The database password is intentionally kept only in the local `mofi-app/.env` file and is never committed to GitHub.

## Release checklist

- Run migrations and tests.
- Confirm `APP_DEBUG=false`.
- Configure HTTPS and trusted proxies.
- Configure queue worker and scheduler.
- Verify database backup and restore procedure.
- Check that secrets are stored in the hosting provider, not in Git.
