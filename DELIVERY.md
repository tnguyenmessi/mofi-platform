# MOFI demo delivery checklist

## Demo scope

- Laravel 13, React 19, Inertia, TypeScript, Recharts and PostgreSQL/Supabase.
- Vietnamese stock data is deterministic demo data. The market page prioritizes a simulated quote board, bid/ask depth, session clock and paper orders; a daily OHLC replay endpoint/component is available as supporting history, not as a replacement for the board.
- Five real seconds advance one simulated tick of five minutes while the session is open. The demo date is fixed at `2026-09-15`.
- `/transactions` is intentionally limited to simulated `DEPOSIT` and `WITHDRAW`. `BUY` and `SELL` are paper orders created in `/market`; only executions create immutable ledger rows.
- No real brokerage, bank, payment, KYC, broker account or real-money transaction is connected.

## Current acceptance status

- Public Railway URL is online and smoke-tested: `/up`, `/`, `/login`, public candles, public Binance market preview and private-route authentication boundaries.
- The live shared market session is currently closed at simulated `14:55` (`tick 54/54`). Read-only inspection is valid; do not submit live financial actions without explicit confirmation.
- SQLite suite: `66 tests / 65 passed / 1 skipped / 935 assertions`.
- PostgreSQL concurrency acceptance: `1 passed / 18 assertions` with native prepares and `1 passed / 18 assertions` with emulated prepares, using a disposable local PostgreSQL cluster on `127.0.0.1:55439` only.
- TypeScript check and Vite production build pass. The build keeps three runtime image-path warnings for `/images/journey.jpg`, `/images/mountains.jpg` and `/images/city.jpg`; they do not block the app build.
- Detailed T01-T14 evidence and remaining production gates are in `docs/demo/ACCEPTANCE_REPORT.md`.

## Presentation flow

1. Open `/` and explain that money, stock prices and orders are simulated.
2. Sign in with a local demo account kept outside Git; show the dashboard summary, portfolio, goals and market data.
3. Open `/market`: select `MOFI`, point out last price, reference, ceiling/floor, matched volume, bid/ask levels, simulated time and the next update countdown.
4. Place a paper `LIMIT` or `MARKET` order only when the local/shared session is open. Explain reservation, partial fill, execution and cancellation.
5. Open `/transactions`: demonstrate only `Nạp tiền ảo` or `Rút tiền ảo`; then show the history table containing cash movements and executed `BUY/SELL` rows for reconciliation.
6. Show ownership, goals, watchlist, alerts, tasks, learning, Copilot, simulation and admin health as time allows.
7. Close with the production boundary: no broker, real Vietnamese exchange feed, real money or paid LLM is enabled.

## Verification commands

```powershell
cd mofi-app
php -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit --colors=never
npx tsc --noEmit
npm run build
git diff --check
git ls-files .env mofi-app/.env mofi-app/.local-demo-credentials.md
```

The PostgreSQL test is opt-in and must target only the disposable local database documented in `docs/demo/DEMO_RUNBOOK.md`; never point it at Railway or Supabase.

## Documentation

- `docs/word/MOFI_Ho_so_du_an_va_huong_dan_su_dung.docx` — dossier for management review.
- `docs/pdf/MOFI_Ho_so_du_an_va_huong_dan_su_dung.pdf` — PDF export of the dossier.
- `docs/demo/DEMO_RUNBOOK.md` — short presentation script and algorithms.
- `docs/demo/DEMO_SCRIPT_DETAILED.md` — click-by-click demo instructions.
- `docs/demo/ACCEPTANCE_REPORT.md` — evidence matrix and open production gates.
