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
