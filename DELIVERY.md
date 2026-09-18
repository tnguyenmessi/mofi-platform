# MOFI demo delivery checklist

## Demo scope

- Laravel 13, React 19, Inertia, TypeScript, Recharts and PostgreSQL/Supabase.
- Stock prices and money are simulated fixtures. Crypto live preview uses the public Binance endpoint when requested.
- No real brokerage, bank, payment, KYC or real-money transaction is connected.

## Presentation flow

1. Open `/` and show the public landing page.
2. Sign in with the local demo account stored in the ignored `mofi-app/.local-demo-credentials.md`.
3. Show `/dashboard`: KPI cards, asset allocation, 30-day chart, goals, market, Copilot and watchlist.
4. Open `/market`, search/filter an instrument and show its 30-day sparkline.
5. Open `/transactions`: show preview, then use the disposable QA account for any Mua/Bán submission.
6. Filter transaction history by kind, symbol and date.
7. Show `/goals`, `/assets`, `/alerts`, `/tasks` and `/learn` user-scoped data.
8. If demonstrating administration, use an admin account and show `/admin`; do not expose credentials.

## Verification completed

- Full PHPUnit: 77 passed, 1 skipped (the opt-in local PostgreSQL concurrency test is run separately).
- TypeScript check and Vite production build pass.
- PostgreSQL concurrency test passes with native and emulated prepares.
- Browser QA passed for a disposable account: deposit 2,000,000 VND, buy 10 MOFI, sell 4 MOFI; history, fees, taxes and balances updated after reload.
- Browser QA passed for market lazy chart loading and transaction filters.
- Admin search/filter and goal deadline/monthly-contribution checks have automated coverage.
- Strategy Studio can save user-scoped allocation records; Investment Lab can save user-scoped shock scenarios without changing portfolio transactions.
- Community now supports user-scoped post creation and deletion, with shared read-only browsing and owner checks.
- MOFI Copilot now saves rule-based question history per user; answers remain deterministic and carry the simulation disclaimer.
- Supabase migration `2026_09_18_140000_create_strategy_and_simulation_tables` is applied and appears as batch 7 in `artisan migrate:status`.
- Production workspace JavaScript initial payload is about 441 KB (about 133 KB gzip); charts load on demand.

## Known boundaries

- The 30-day stock history is demo data and is labeled as such.
- Copilot is deterministic and read-only; it is not a trained or paid LLM.
- Community posts and Copilot history are application-managed tables with direct Postgres API access revoked and RLS enabled on PostgreSQL.
- Real market providers, LLM integration, CI/CD deployment and production monitoring remain separate follow-up work.

## Local commands

```powershell
cd mofi-app
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php -d extension=pdo_sqlite vendor/bin/phpunit --colors=never
npx tsc --noEmit
npm run build
```
