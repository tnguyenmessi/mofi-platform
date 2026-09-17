# API and Integration Design

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

## Application boundary

The browser calls Laravel routes or Inertia actions. Laravel validates input, authorizes the user, applies domain services, and returns typed page props or JSON responses.

## Planned endpoints

- `POST /register`
- `POST /login`
- `GET /dashboard`
- `GET /portfolio`
- `POST /transactions`
- `GET /market/quotes`
- `GET /goals`
- `POST /goals`
- `POST /ai/conversations`

## Market provider contract

Create a `MarketDataProvider` interface with quote and historical-price methods. Implement a mock provider first. Add a real provider only after confirming licensing, rate limits, symbol coverage, delayed versus realtime prices, and redistribution rights.

## Background work

Use Laravel jobs for imports, portfolio snapshots, alert evaluation, and notifications. Use the scheduler to run jobs at the required interval. Cache frequently requested quotes and invalidate them when a fresh import succeeds.
