# MOFI — Kịch bản demo với sếp

## Mục tiêu

Chứng minh ba năng lực: nền tảng tài chính cá nhân, nghiệp vụ paper-trading an toàn ở backend và giao diện thị trường có bảng giá/bid-ask/đồng hồ mô phỏng. Toàn bộ tiền, giá và lệnh trong buổi demo là mô phỏng.

## Chuẩn bị

1. Dùng Railway public URL để trình bày read-only, hoặc chạy local:

```powershell
cd "C:\Users\nguye\Downloads\DỰ ÁN 2\mofi-app"
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

2. Tài khoản demo nằm trong `mofi-app/.local-demo-credentials.md` — file local bị Git ignore, không chiếu hoặc commit.
3. Session đầu tiên dùng ngày gốc `2026-09-15`. Nhịp mặc định là 5 giây thật cho mỗi tick 5 phút mô phỏng; đủ 54 tick thì tự sang ngày làm việc tiếp theo.
4. Railway là môi trường dùng chung, nên chỉ gửi deposit/withdraw/order/cancel sau khi có xác nhận ngay trước thao tác.

## Kịch bản 10 phút

### 1. Landing — 1 phút

Giới thiệu MOFI là không gian quản lý tài chính và đầu tư thực hành. Chỉ vào nhãn dữ liệu mô phỏng, không gọi đây là sàn chứng khoán thật.

### 2. Dashboard — 2 phút

Đăng nhập Demo A và chỉ vào tổng tài sản, tiền mặt khả dụng, tiền đang giữ, danh mục, P/L, mục tiêu, watchlist và lệnh chờ. Giải thích: Laravel tính summary từ ledger + quote mô phỏng; React/Inertia chỉ hiển thị.

### 3. Thị trường — 2 phút

Mở `/market`, chọn `MOFI`, chỉ vào:

- Giá khớp cuối, tham chiếu, trần, sàn, khối lượng.
- Bảng Ask 1-3 và Bid 1-3.
- Ngày, giờ mô phỏng, tick hiện tại/tổng tick và thời gian cập nhật.
- Nến OHLC ngày, volume và badge `Mô phỏng`.

Binance chỉ là panel crypto tham khảo tách biệt, định giá USDT, không đi vào danh mục VND.

### 4. Paper trading — 2 phút

Khi local session còn mở:

1. Chọn `Mua` hoặc `Bán`, loại `LO` hoặc `MP`, nhập khối lượng.
2. LO BUY chỉ khớp khi Ask <= giá đặt; LO SELL chỉ khớp khi Bid >= giá đặt.
3. MP ăn các mức đối ứng trong depth; lệnh lớn có thể `PARTIALLY_FILLED`.
4. Chỉ vào reservation, execution, trạng thái order và số dư khả dụng.
5. Hủy lệnh `OPEN/PARTIALLY_FILLED` để chứng minh reservation được giải phóng.

### 5. Nạp/rút và lịch sử — 1 phút

Mở `Nạp / rút tiền`. Form chỉ có `Nạp tiền ảo` và `Rút tiền ảo`; không chọn Mua/Bán tại đây. Sau khi một paper order khớp, lịch sử vẫn hiển thị dòng BUY/SELL để đối soát, nhưng không tạo trực tiếp từ form này.

### 6. Các module khác — 1 phút

Chỉ nhanh goals, watchlist, alerts, tasks, learning, Copilot, strategies, simulation, community và admin. Nhấn mạnh Copilot/strategy/simulation là deterministic/mock, simulation không ghi vào portfolio thật.

### 7. Kết luận — 1 phút

“MOFI đã có luồng khép kín từ bảng giá mô phỏng đến order, reservation, execution, ledger và dashboard. Bản này chưa dùng broker, tiền thật, dữ liệu exchange realtime hay LLM trả phí.”

## Thuật toán cần giải thích

### Portfolio

```text
cash = sum(cash_delta)
market_value = quantity * latest_demo_quote
unrealized_pnl = market_value - remaining_cost_basis
total_pnl = realized_pnl + unrealized_pnl + net_dividend
total_assets = cash + market_value + manual_assets
```

Deposit không phải lợi nhuận. BUY tăng cost basis bằng gross + fee + tax; SELL giải phóng average cost theo lượng bán.

### Đặt lệnh

1. Authorize portfolio thuộc user.
2. Lock instrument và portfolio.
3. Kiểm tra request key/hash, session và quote hiện tại.
4. Tính available cash/quantity sau reservation đang mở.
5. Tạo order + reservation trong cùng transaction.
6. Match ngay với tick hiện tại nếu đủ điều kiện.

### Khớp lệnh

- MARKET BUY: Ask 1 → Ask 3.
- MARKET SELL: Bid 1 → Bid 3.
- LIMIT BUY: Ask <= limit price.
- LIMIT SELL: Bid >= limit price.
- Mỗi fill tạo đúng một execution và một transaction; phần còn lại vẫn được giữ.
- Khi hoàn tất hoặc hủy, reservation được giải phóng.

### Đồng hồ mô phỏng

Server giữ `current_tick`, `simulated_at`, `last_advanced_at` và `revision`. Frontend polling gọi board mỗi 2 giây; khi đủ 5 giây server tiến tối đa một tick. Reload không chạy lại tick đã xử lý; khi đủ 54 tick server đóng ngày, hủy lệnh còn treo và mở ngày làm việc kế tiếp ở tick 0.

### Idempotency và concurrency

Cùng request key + cùng payload trả kết quả cũ; cùng key + payload khác trả `409`. Portfolio/order lock ngăn hai request dùng chung cash hoặc quantity. PostgreSQL acceptance test đã chạy đạt ở native và emulated prepares.

## Câu hỏi sếp có thể hỏi

- **Đây có phải giao dịch thật không?** Không; đây là paper trading, không broker, ngân hàng hay tiền thật.
- **Tại sao không gọi giá cổ phiếu Việt Nam thật?** Cần provider hợp pháp, SLA, rate limit và quyền dữ liệu; demo dùng replay ổn định, có nhãn.
- **Tại sao BUY/SELL không nằm ở Transactions?** Transactions là cash flow; Market là order flow. Execution mới sinh ledger BUY/SELL, tránh ghi tiền hai lần và giữ đúng logic khớp lệnh.
- **React có tự tính số dư không?** Không; server là source of truth, dùng decimal/BigDecimal.
- **Làm sao chống bấm hai lần?** Request key, request hash, unique constraint và row lock.
- **AI đã thật chưa?** Copilot hiện rule-based, không tự nhận là LLM.
- **Mở rộng production thế nào?** Thay provider replay bằng market-data provider hợp pháp, thêm broker adapter, KYC/2FA, audit, monitoring, limits và kiểm thử SLA.

## Kiểm thử trước demo

```powershell
cd mofi-app
php -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit --colors=never
npx tsc --noEmit
npm run build
```

Kết quả đã xác nhận: `67 tests / 66 passed / 1 skipped / 945 assertions`; PostgreSQL concurrency `1 passed / 18 assertions` ở mỗi chế độ prepares. Test PostgreSQL chỉ được chạy trên database disposable local `mofi_transaction_test` ở `127.0.0.1:55439`.
