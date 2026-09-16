# MOFI Database Design

## Core tables

`users`, `profiles`, `assets`, `portfolios`, `transactions`, `asset_holdings`, `portfolio_snapshots`, `financial_goals`, `goal_contributions`, `watchlists`, `watchlist_items`, `market_quotes`, `market_indices`, `alerts`, `notifications`, `ai_conversations`, `ai_messages`, `learning_courses`, `learning_lessons`, `subscriptions`.

## Key relationships

- A user owns portfolios, transactions, goals, watchlists, alerts, and conversations.
- A portfolio contains holdings and time-based snapshots.
- An asset can have many transactions, holdings, and market quotes.
- A goal has many contributions.
- A conversation has many AI messages.

## Financial data rules

- Use `numeric`/decimal columns for money and prices.
- Store quantities with an appropriate decimal scale.
- Store timestamps in UTC and format them for Vietnam in the UI.
- Preserve original transactions; calculate holdings from an auditable transaction history.
- Record fees and taxes separately where the provider or business rules require them.
- Add indexes for user ownership, asset symbol, transaction date, and quote timestamp.

## Supabase configuration

The Laravel `.env` will contain the Supabase PostgreSQL host, port, database, username, and password. The Supabase service key must never be committed or sent to the browser. Use pooled connections in production when the hosting provider recommends it.

## Planned migrations

Start with users/profiles, assets, portfolios, transactions, holdings, and snapshots. Add market data, goals, alerts, learning, AI, and subscriptions in later migrations so each change can be reviewed and rolled back.
