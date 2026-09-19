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

### Final acceptance pass (2026-09-19)

- Fixed local demo login configuration: the `#` in `DEMO_LOGIN_PASSWORD` is now quoted in `mofi-app/.env`; after reseeding, `demo@mofi.local` and the documented password authenticate successfully.
- Browser smoke test passed on `http://127.0.0.1:8000`: login, dashboard, all 15 authenticated routes, market, transactions and admin.
- Authenticated route matrix passed with no server error, browser error logs or horizontal overflow: `/assets`, `/portfolio`, `/transactions`, `/goals`, `/market`, `/watchlist`, `/alerts`, `/notifications`, `/tasks`, `/copilot`, `/strategies`, `/simulation`, `/learn`, `/community`, `/settings`.
- Market UI visibly exposes the simulated OHLC candle chart, 7/14/30-day ranges, volume/depth area, paper-order form and the separate Binance public crypto preview.
- Admin smoke test passed: admin login, `/admin`, health check (`Database`, `Cache`, `Danh mục`, `Giá mô phỏng` all `Sẵn sàng`), account table and instrument catalogue.
- Full final checks passed: PHPUnit `96 tests / 95 passed / 1 skipped / 1092 assertions`, TypeScript check, and Vite production build. The one skipped test is the opt-in PostgreSQL concurrency suite; it was previously executed separately and passed.

- Full PHPUnit: 96 tests, 95 passed, 1 skipped with the bundled SQLite extension enabled; the opt-in local PostgreSQL concurrency test is run separately.
- TypeScript check and Vite production build pass.
- PostgreSQL concurrency test passes with native and emulated prepares.
- Browser QA passed for a disposable account: deposit 2,000,000 VND, buy 10 MOFI, sell 4 MOFI; history, fees, taxes and balances updated after reload.
- Browser QA passed for market lazy chart loading and transaction filters.
- Admin search/filter and goal deadline/monthly-contribution checks have automated coverage.
- Transaction receipts, owner-scoped CSV export, admin health checks, market provider abstraction and alert service extraction are covered by the latest suite.
- Landing mockup pointer interaction, transaction receipt animation and holdings search were added with reduced-motion support.
- Paper trading P0 now has orders, reservations, executions, market/limit matching, cancel/replay, order status filtering and a Market order form; all values remain simulated.
- Portfolio summary exposes `reserved_cash`, `available_cash`, `reserved_quantity` and `available_quantity` so pending orders are visible in the same server-calculated snapshot.
- Market now exposes a deterministic `/api/v1/instruments/{instrument}/candles` OHLC replay endpoint and `/market` renders the simulated candle strip with a clear demo label.
- `/market` now also renders a simulated bid/ask depth panel, explicitly labeled as non-market liquidity.
- Strategy Studio can save user-scoped allocation records; Investment Lab can save user-scoped shock scenarios without changing portfolio transactions.
- Community now supports user-scoped post creation and deletion, with shared read-only browsing and owner checks.
- MOFI Copilot now saves rule-based question history per user; answers remain deterministic and carry the simulation disclaimer.
- Supabase migration `2026_09_18_140000_create_strategy_and_simulation_tables` is applied and appears as batch 7 in `artisan migrate:status`.
- Production workspace JavaScript initial payload is about 441 KB (about 133 KB gzip); charts load on demand.
- The latest build includes keyboard skip navigation, visible focus states, reduced-motion support and page transition feedback.

## Known boundaries

- The 30-day stock history is demo data and is labeled as such.
- Copilot is deterministic and read-only; it is not a trained or paid LLM.
- Community posts and Copilot history are application-managed tables with direct Postgres API access revoked and RLS enabled on PostgreSQL.
- GitHub Actions CI now checks Pint, PHPUnit, TypeScript and the production Vite build on pushes and pull requests.
- The candle strip now includes a lightweight volume histogram and interactive ranges. Multi-tick UI can call the deterministic advance endpoint, and partial-fill replay is covered by feature tests.
- Paper orders can now advance through a deterministic replay tick and partially fill against the simulated tick capacity; remaining quantity stays reserved until completion or cancellation. The server rejects replaying an already processed tick and supports cancelling a partially filled order.
- Real market providers, LLM integration, CI/CD deployment and production monitoring remain separate follow-up work.

## Demo runbook

- Kịch bản trình bày, thuật toán, câu hỏi phản biện và lệnh kiểm thử: `docs/demo/DEMO_RUNBOOK.md`.
- Tài khoản/mật khẩu local: `mofi-app/.local-demo-credentials.md` (Git ignored, không public).

## Local commands

```powershell
cd mofi-app
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php -d extension=pdo_sqlite vendor/bin/phpunit --colors=never
npx tsc --noEmit
npm run build
```
