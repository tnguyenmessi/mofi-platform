# MOFI — Kế hoạch nâng cấp toàn diện và Paper Trading

## 1. Mục tiêu cuối cùng

Xây MOFI thành dashboard tài chính mô phỏng có trải nghiệm gần hai ảnh tham chiếu:

- Landing page rõ câu chuyện sản phẩm.
- Dashboard sau đăng nhập có KPI, biểu đồ, mục tiêu, thị trường, Copilot, watchlist, cảnh báo và task.
- Màn hình paper trading có bảng giá, biểu đồ theo thời gian, lệnh mua/bán, lệnh chờ, khớp lệnh và lịch sử execution.
- Backend tính tiền và vị thế chính xác, mọi dữ liệu tách theo user.
- Không giao dịch tiền thật, không kết nối broker thật trong phạm vi demo.

Luồng nghiệp vụ đích:

`Market data → Order → Reservation → Execution → Transaction → Portfolio summary → Dashboard`

## 2. Nguyên tắc kiến trúc

- Laravel giữ route, auth, policy, validation, transaction database và nghiệp vụ tài chính.
- PostgreSQL/Supabase giữ dữ liệu chính; browser không truy cập trực tiếp bảng private.
- React/Inertia hiển thị state; không để frontend quyết định số dư cuối cùng.
- Mọi tiền, giá, phí, thuế và số lượng dùng `numeric/decimal` cùng `BigDecimal` ở server.
- Giá chứng khoán Việt Nam dùng fixture/replay có nhãn `is_demo=true`.
- Binance crypto preview tiếp tục tách qua `MarketDataProvider`.
- Mọi truy vấn private phải owner-scoped.
- Paper trading phải idempotent, immutable và có audit trail.

## 3. Giai đoạn 0 — Baseline và chuẩn bị

### Công việc

1. Chốt phạm vi demo: tiền ảo, giá chứng khoán mô phỏng, không broker thật.
2. Giữ migration hiện có, backup schema và fixture hiện tại.
3. Kiểm tra các invariant đang có: cash không âm, vị thế không âm, transaction immutable, request key unique.
4. Đặt ngày mô phỏng cố định và timezone thống nhất.
5. Tạo interface provider cho quote, candle và market session.
6. Chụp baseline dashboard, transactions, market và admin để so sánh sau mỗi giai đoạn.

### Đầu ra

- Tài liệu schema và flow được cập nhật.
- Fixture reset được về cùng một trạng thái.
- Không có secret trong Git.
- Test hiện tại vẫn đạt trước khi mở rộng.

## 4. Giai đoạn 1 — Chuẩn hóa market data

### Database

Giữ `instruments` và `market_prices`, bổ sung khi cần:

- `market_candles`: instrument, interval, candle_time, open, high, low, close, volume, source, is_demo.
- Unique `(instrument_id, interval, candle_time, source)`.
- Index `(instrument_id, interval, candle_time)`.

### Backend

1. `QuoteProvider` trả quote hiện tại, timestamp, source và status.
2. `CandleProvider` trả OHLCV theo interval.
3. `DemoReplayProvider` phát dữ liệu fixture theo clock mô phỏng.
4. Bỏ qua candle thiếu, cũ hoặc sai thứ tự.
5. Cache quote/candle với TTL rõ ràng.
6. Không cho quote thật đi vào danh mục VND nếu chưa bật cờ cấu hình.

### Frontend

- Market tabs: Việt Nam, thế giới, hàng hóa, crypto.
- Quote detail: giá, thay đổi, nguồn, thời điểm dữ liệu.
- Mini chart và chart OHLC.
- Badge “Dữ liệu mô phỏng”.

### Kiểm thử

- Provider trả dữ liệu đúng thứ tự.
- Cache hit không làm thay đổi kết quả.
- Provider lỗi trả trạng thái rõ, không làm trắng dashboard.
- Không trộn crypto USD với cổ phiếu VND.

## 5. Giai đoạn 2 — Nâng cấp portfolio trước trading

### Backend

1. Chuẩn hóa `PortfolioSummary` thành các phần: cash, available_cash, reserved_cash, holdings, reserved_quantity, market_value, cost_basis, realized_pnl, unrealized_pnl.
2. Tách tiền khả dụng khỏi tiền đang giữ cho lệnh mở.
3. Tách số lượng khả dụng khỏi số lượng đang giữ cho lệnh bán.
4. Thêm filter theo symbol và range thời gian.
5. Giữ công thức:

```text
market_value = quantity × latest_close
unrealized_pnl = market_value - remaining_cost_basis
total_pnl = realized_pnl + unrealized_pnl + net_dividend
total_assets = cash + market_value + manual_assets
```

### Frontend

- KPI: tổng tài sản, tiền khả dụng, danh mục, lãi/lỗ hôm nay.
- Holdings table: mã, lượng, giá vốn, giá hiện tại, market value, P/L.
- Tabs chart: 1D, 1W, 1M, 3M.
- Tooltip ghi rõ đơn vị và ngày dữ liệu.

### Kiểm thử

- Deposit không làm tăng P/L.
- BUY giảm cash khả dụng và tăng vị thế.
- SELL giảm vị thế và tính realized P/L đúng.
- Manual asset không làm thay đổi securities P/L.
- Thiếu quote hiển thị `Chưa đủ giá`, không biến thành 0.

## 6. Giai đoạn 3 — Mô hình order và reservation

### Bảng `orders`

- `id`, `user_id`, `portfolio_id`, `instrument_id`.
- `side`: BUY/SELL.
- `order_type`: MARKET/LIMIT.
- `quantity`, `limit_price`, `filled_quantity`.
- `status`: OPEN/PARTIALLY_FILLED/FILLED/CANCELLED/REJECTED/EXPIRED.
- `request_key`, `request_hash`, `created_at`, `cancelled_at`, `filled_at`.
- Unique `(portfolio_id, request_key)`.

### Bảng `order_reservations`

- `order_id`, `portfolio_id`, `cash_amount`, `quantity`.
- `released_at`.
- Unique `order_id`.

### Quy tắc

- BUY giữ `gross + estimated_fee + tax`.
- SELL giữ số lượng cổ phiếu.
- Lệnh OPEN không được giữ vượt cash hoặc quantity khả dụng.
- Cancel hoặc reject phải giải phóng reservation.
- Mọi thay đổi order và reservation nằm trong cùng database transaction.

## 7. Giai đoạn 4 — Execution engine mô phỏng

### Khớp lệnh

- MARKET BUY khớp theo ask mô phỏng tiếp theo.
- MARKET SELL khớp theo bid mô phỏng tiếp theo.
- LIMIT BUY khớp khi ask `<= limit_price`.
- LIMIT SELL khớp khi bid `>= limit_price`.
- Bản đầu khớp toàn bộ; partial fill để sau.
- Không dùng dữ liệu tương lai tại thời điểm đặt lệnh.

### Transaction flow

1. Authorize portfolio.
2. Lock portfolio và order.
3. Kiểm tra order còn OPEN.
4. Lấy quote hợp lệ của phiên mô phỏng.
5. Tính execution price, gross, fee, tax, cash delta.
6. Tạo một execution duy nhất.
7. Tạo transaction tương ứng.
8. Cập nhật filled quantity/status.
9. Giải phóng phần reservation còn lại.
10. Commit rồi invalidate portfolio cache.

### Chống lỗi

- Retry cùng request key trả order/receipt cũ.
- Khớp cùng order hai lần bị unique execution chặn.
- Hai lệnh cạnh tranh cùng cash được serialize bằng row lock.
- Lỗi giữa execution và transaction rollback toàn bộ.

## 8. Giai đoạn 5 — Giao diện paper trading

### Bố cục

- Header: mã, giá hiện tại, thay đổi, badge mô phỏng.
- Trái: chart nến + volume + interval.
- Phải: form Mua/Bán, market/limit, quantity, price, phí dự kiến.
- Dưới chart: bảng bid/ask mô phỏng.
- Bên dưới: tab Lệnh mở, Lịch sử khớp, Lịch sử transaction.

### Tương tác

- Preview tổng tiền và cash/quantity còn lại.
- Disable submit khi thiếu dữ liệu hoặc không đủ sức mua.
- Toast/receipt sau đặt lệnh.
- Cancel order có xác nhận rõ.
- Loading/error/empty state cho chart và order book.
- Không để bảng làm tràn toàn trang; table chỉ scroll trong container.

### Sidebar/dashboard

- Sidebar desktop thu gọn/mở rộng.
- Mobile dùng drawer.
- Dashboard card “Lệnh đang chờ” và “Tiền đang giữ”.
- Market widget liên kết trực tiếp đến paper trading detail.

## 9. Giai đoạn 6 — Nâng cấp các module liên quan

### Watchlist

- Mini chart theo cùng `CandleProvider`.
- Link mở thẳng màn hình mã.
- Không tạo duplicate watchlist.

### Alerts

- Alert theo giá và phần trăm thay đổi.
- Dùng quote cùng phiên với chart.
- Giữ transition false → true, re-arm và stale quote handling.

### Goals

- Số tiền cần mỗi tháng.
- Trạng thái hoàn thành/quá hạn.
- Không tự động trừ cash khi chỉ cập nhật tiến độ.

### Copilot

- Rule-based đọc summary/order read-only.
- Không được tự đặt lệnh.
- Nếu thêm AI thật, bắt buộc provider interface, timeout, budget và disclaimer.

### Admin

- Theo dõi orders/executions.
- Filter audit log.
- Provider health, cache health, số order lỗi và thời gian phản hồi.

## 10. Giai đoạn 7 — Test matrix

### Backend

- Unit: money, fee, tax, average cost, limit matching.
- Feature: create/cancel/fill order, receipt, filters, CSV.
- Security: guest, member, admin, cross-owner.
- Concurrency: hai BUY dùng chung cash, hai SELL dùng chung quantity, duplicate request.
- Provider: valid, stale, malformed, timeout, cache.

### Frontend

- TypeScript.
- Production build.
- Chart loading/error/empty.
- Sidebar expanded/collapsed.
- Viewport 1366, 1024, 768, 390.
- No horizontal page overflow.
- Keyboard focus và reduced motion.

### Acceptance criteria

- Một lệnh chỉ tạo tối đa một execution và một transaction.
- Cash/quantity reservation luôn khớp với order OPEN.
- Cancel giải phóng reservation.
- Reload không làm mất order/history.
- User A không thấy order của User B.
- Chart và bảng giá dùng cùng quote source.
- Demo có thể chạy hoàn toàn bằng fixture không cần API ngoài.

## 11. Giai đoạn 8 — Demo và bàn giao

1. Landing: giới thiệu sản phẩm.
2. Dashboard: chỉ KPI, allocation, goals, market, Copilot.
3. Thu gọn sidebar để trình bày toàn màn hình.
4. Mở market detail, chọn mã MOFI.
5. Đặt limit BUY dưới giá hiện tại, cho thấy order OPEN và tiền bị giữ.
6. Chạy replay đến tick đạt giá, cho thấy FILLED và transaction mới.
7. Mở portfolio chứng minh cash, holdings và P/L thay đổi.
8. Hủy một order khác để chứng minh tiền được giải phóng.
9. Mở admin health/audit.
10. Kết luận rõ đây là paper trading mô phỏng, chưa phải giao dịch thật.

## 12. Thứ tự ưu tiên

### P0 — Bắt buộc trước paper trading

- Market provider contract.
- Portfolio available/reserved balances.
- Orders, reservations, executions schema.
- Matching service và transaction integration.
- Test concurrency/security.
- Không tràn layout.

### P1 — Làm ngay sau lõi

- Chart nến, order book, order tabs.
- Watchlist/alerts nối cùng quote.
- Dashboard pending orders và available cash.
- Sidebar collapse và polish giao diện.

### P2 — Mở rộng sau demo

- Partial fill, slippage, nhiều phiên giao dịch.
- Benchmark, drawdown, CAGR.
- AI provider thật.
- Broker integration, KYC, 2FA, tiền thật.

## 13. Definition of Done

Đợt paper trading chỉ được xem là hoàn tất khi:

- Migration chạy được trên PostgreSQL/Supabase.
- Fixture replay tái hiện cùng kết quả sau reset.
- Đặt, giữ, khớp, hủy và retry đều có test.
- Summary sau execution khớp ledger.
- Browser không tràn ở desktop và mobile cơ bản.
- Full PHPUnit, PostgreSQL concurrency, TypeScript và Vite build đều đạt.
- Tài liệu demo cập nhật tài khoản, flow, giới hạn và cách giải thích.
- Không có credential hoặc API key trong commit.
