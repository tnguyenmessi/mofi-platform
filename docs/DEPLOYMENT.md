# Deployment Plan

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
