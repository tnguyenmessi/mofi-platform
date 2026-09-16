# MOFI Project Plan

## Product goal

Build a Vietnamese personal finance and investment platform with a public marketing site and an authenticated dashboard. The first release uses sample market data, then adds a replaceable market-data provider.

## Delivery phases

1. **Foundation:** install PHP, Composer, Laravel, Node.js; initialize GitHub repository; define environment variables and coding conventions.
2. **Public website:** reproduce the MOFI landing page, responsive layout, reusable sections, and registration calls to action.
3. **Authentication:** registration, login, password reset, profile, and user/admin authorization.
4. **Dashboard:** asset metrics, portfolio allocation, goals, market table, watchlist, alerts, and AI summary using seeded data.
5. **Portfolio domain:** assets, transactions, holdings, snapshots, deposits, withdrawals, fees, and profit/loss calculations.
6. **Market integration:** provider interface, scheduled imports, caching, historical prices, and provider failure handling.
7. **Goals and learning:** financial goals, contributions, courses, lessons, and progress.
8. **AI Copilot:** secure context building, conversations, usage limits, and audit logging.
9. **Admin and subscriptions:** content management, users, plans, feature gates, and operational views.
10. **Quality and release:** tests, security review, responsive QA, performance checks, backups, monitoring, and production deployment.

## Definition of the first MVP

- Public landing page
- Registration and login
- Dashboard with seeded data
- Manual asset and transaction entry
- Portfolio allocation and profit/loss charts
- Financial goals
- Mock market-data provider
- Responsive desktop and mobile layouts

## Working principles

- Keep financial calculations in tested domain services, not in UI components.
- Store monetary values as PostgreSQL `numeric`/Laravel decimal values.
- Keep market-data providers behind an interface so vendors can be changed.
- Never expose database credentials or AI keys to the browser.
- Use fake data until data licensing and provider contracts are confirmed.
