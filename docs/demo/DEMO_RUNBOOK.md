# MOFI — Kịch bản demo với sếp

## Mục tiêu trình bày

Chứng minh ba năng lực: thiết kế sản phẩm theo ảnh tham chiếu, xây logic tài chính an toàn ở backend và nối dữ liệu vào giao diện có biểu đồ. Tất cả tiền và giá cổ phiếu Việt Nam trong buổi demo là dữ liệu mô phỏng.

## Chuẩn bị trước buổi demo

1. Mở terminal 1 và chạy `cd mofi-app; php artisan serve --host=127.0.0.1 --port=8000`.
2. Mở terminal 2 và chạy `cd mofi-app; npm run dev`.
3. Mở `http://127.0.0.1:8000/`.
4. Tài khoản và mật khẩu nằm trong file local bị Git ignore: `mofi-app/.local-demo-credentials.md`. Không chiếu file này lên màn hình hoặc đưa vào GitHub.
5. Nếu gặp `429 Too Many Requests`, không thử liên tục; chạy `php artisan cache:clear` rồi chờ khoảng một phút.
6. Phương án dự phòng: chạy frontend bằng manifest production sau `npm run build`, giữ Laravel server đang chạy.

## Kịch bản 10 phút

### 1. Landing — 1 phút

Mở trang chủ và nói: “MOFI là không gian tài chính cá nhân, tập trung vào tài sản, mục tiêu, học đầu tư và giao dịch thực hành. Bố cục lấy cảm hứng từ hai ảnh mẫu nhưng dữ liệu trong bản này được gắn nhãn mô phỏng.” Chỉ nhanh hero, mockup dashboard, market preview và các nhóm chức năng.

### 2. Đăng nhập và dashboard — 2 phút

Đăng nhập tài khoản demo. Chỉ ra bốn KPI: tổng tài sản, tiền mặt, chứng khoán và lãi/lỗ. Mở donut phân bổ, goal progress, market table, Copilot và portfolio chart. Nói rõ các card không tự tính riêng ở frontend; Laravel lấy cùng nguồn transaction + market fixture rồi gửi props cho React.

### 3. Giao dịch mua — 2 phút

Vào `/transactions`, chọn `Mua`, chọn mã `MOFI`, nhập số lượng nhỏ và giữ giá mẫu. Chỉ vào phần preview: gross, phí, thuế, tổng thanh toán và số dư dự kiến. Gửi giao dịch, chỉ receipt, reload rồi mở lịch sử.

Giải thích: “Server mới là nơi quyết định số tiền. Frontend chỉ preview. Portfolio được khóa trong transaction database, kiểm tra tiền và vị thế trước khi insert. Nếu mạng chập chờn bấm lại, cùng request key chỉ tạo một dòng.”

### 4. Trường hợp lỗi — 1 phút

Thử bán số lượng lớn hơn vị thế hoặc rút nhiều hơn tiền mặt. Kết quả phải là lỗi validation, không thêm transaction dở dang. Có thể nói thêm cùng request key khác payload sẽ trả conflict để chống ghi trùng.

### 5. Market, watchlist, alert — 1 phút

Mở `/market`, tìm mã, xem sparkline và thêm watchlist. Tạo cảnh báo GTE/LTE rồi bấm kiểm tra dữ liệu mô phỏng. Mở notifications. Giải thích cảnh báo chỉ tạo khi trạng thái chuyển từ false sang true; trạng thái true lặp lại không spam notification.

### 6. Simulation, Copilot, admin — 2 phút

Mở `/simulation`, kéo shock 10–20% và lưu kịch bản. Nói rõ đây là phép tính what-if, không sửa số dư hay transaction. Mở `/copilot`, chọn câu hỏi mẫu và nói đây là rule-based, chưa phải LLM trả phí. Nếu còn thời gian mở `/admin`, dùng tài khoản admin, bấm health check và chỉ ra database/cache/demo fixture.

### 7. Kết luận — 1 phút

Chốt: “Bản demo đã có luồng dữ liệu khép kín từ route → policy → service → PostgreSQL → props → React chart. Các phần chưa bật là broker/tiền thật, chứng khoán realtime Việt Nam và LLM thật; chúng được tách thành provider hoặc roadmap riêng để không giả lập sai trong bản đánh giá.”

## Logic và thuật toán cần giải thích

### Tổng tài sản và P/L

- Cash = tổng `cash_delta` của các transaction.
- Vị thế = tổng BUY trừ tổng SELL theo từng mã.
- Market value = quantity × giá mô phỏng gần nhất.
- Tổng tài sản = cash + market value chứng khoán + manual assets.
- Realized P/L của SELL = tiền thực nhận − giá vốn của lượng bán.
- Unrealized P/L = market value hiện tại − cost basis còn lại.
- Total P/L = realized P/L + unrealized P/L + net dividend. Deposit không phải lợi nhuận.
- Tiền dùng `numeric/decimal` và `BigDecimal` ở server, không dùng JavaScript float để quyết định số dư.

### Giá vốn bình quân

Với BUY, giá vốn tăng bằng `gross + fee + tax`. Với SELL, giá vốn phần bán là `average_cost × quantity_sold`; phần còn lại giữ giá vốn tương ứng. Khi bán hết, giá vốn vị thế về 0.

### Giao dịch an toàn

1. Authorize portfolio thuộc user hiện tại.
2. Normalize và validate input.
3. Khóa portfolio bằng row lock.
4. Kiểm tra request key đã tồn tại chưa.
5. Tính cash/quantity từ lịch sử.
6. Chặn cash âm hoặc vị thế âm.
7. Insert transaction immutable.
8. Tính lại summary sau commit.

Nếu bất kỳ bước nào lỗi, transaction database rollback toàn bộ.

### Idempotency

Client tạo `request_key`. Server hash payload nghiệp vụ. Cùng key và cùng payload trả lại receipt cũ; cùng key nhưng payload khác trả `409 Conflict`. Cơ chế này bảo vệ khi người dùng bấm lại sau timeout.

### Alert

Rule có operator GTE/LTE và `last_condition`. Giá thiếu hoặc không đúng ngày mô phỏng thì bỏ qua. `false → true` tạo notification; `true → true` không tạo thêm; khi về false, rule được re-arm cho lần đạt ngưỡng tiếp theo.

### Simulation

Với shock giảm `r`, giá trị chứng khoán sau kịch bản = giá trị hiện tại × `(1 - r)`. Cash và manual assets giữ nguyên. Kết quả chỉ lưu scenario, không ghi transaction và không thay portfolio thật.

## Câu hỏi sếp có thể hỏi

- **Tại sao chưa gọi API chứng khoán thật?** Vì cần nguồn hợp pháp, rate limit và chất lượng dữ liệu; bản demo dùng fixture ổn định, có nhãn `demo`. Crypto preview đã tách provider riêng để chứng minh khả năng thay nguồn.
- **Tại sao không tính tiền ở React?** React chỉ preview; server là nguồn sự thật để tránh sửa request hoặc sai số float.
- **Làm sao chống user xem dữ liệu người khác?** Policy, query theo `user_id`, route model binding và test cross-owner đều chặn.
- **Làm sao chống bấm nút hai lần?** Request key + hash + unique constraint + row lock.
- **AI đã train chưa?** Chưa. Copilot hiện deterministic/rule-based, không giả là mô hình AI thật.
- **Nếu database lỗi thì sao?** Transaction rollback, không lưu nửa chừng; admin health endpoint báo trạng thái database/cache.
- **Có dùng tiền thật không?** Không. Đây là sổ giao dịch mô phỏng, không có broker, ngân hàng hay thanh toán.
- **Mở rộng production thế nào?** Thay `MarketDataProvider`, thêm queue/cache/observability, KYC/2FA, broker adapter, audit và kiểm thử SLA trước khi bật giao dịch thật.

## Lệnh kiểm thử trước demo

```powershell
cd mofi-app
php -d extension=pdo_sqlite vendor/bin/phpunit --colors=never
$env:MOFI_PG_TEST='1'; $env:MOFI_PG_EMULATE_PREPARES='0'; php vendor/bin/phpunit tests/Feature/PostgresTransactionConcurrencyTest.php
$env:MOFI_PG_EMULATE_PREPARES='1'; php vendor/bin/phpunit tests/Feature/PostgresTransactionConcurrencyTest.php
npx tsc --noEmit
npm run build
```

Kết quả nghiệm thu hiện tại: full suite `84 passed, 1 skipped` mặc định; PostgreSQL concurrency `1 passed, 16 assertions` ở mỗi chế độ; TypeScript và Vite build đạt.
