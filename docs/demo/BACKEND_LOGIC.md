# MOFI Backend Logic

Tài liệu này mô tả các quy tắc backend cần giữ khi mở rộng dự án. Dữ liệu demo là tiền ảo và giá mô phỏng, không đặt lệnh tài chính thật.

## 1. Kiến trúc xử lý

`Browser -> Laravel route/middleware -> FormRequest/policy -> Controller -> Service -> PostgreSQL/Supabase -> JSON/Inertia response`.

- Laravel quản lý session, CSRF, rate limit, validation, authorization và truy vấn database.
- React/Inertia chỉ hiển thị dữ liệu; browser không kết nối trực tiếp Supabase.
- Tiền, giá, phí, thuế và giá vốn dùng `numeric`/`BigDecimal`; JSON trả số tiền dạng chuỗi.
- Ngày gốc của fixture nằm ở `config/demo.php`; session Market tự chuyển sang ngày làm việc kế tiếp sau khi đủ 54 tick, còn lịch sử giao dịch vẫn giữ nguyên ngày đã khớp.

## 2. Tính danh mục

Dịch các giao dịch theo `trade_date`, sau đó theo `id`:

- `DEPOSIT`: tăng cash, không tăng lợi nhuận.
- `WITHDRAW`: giảm cash; không được làm cash âm.
- `BUY`: cash giảm `gross + fee + tax`; quantity tăng; cost basis tăng cùng số tiền đó.
- `SELL`: cash tăng `gross - fee - tax`; quantity giảm; cost basis giải phóng theo `basis * quantity / current_quantity`, làm tròn tối đa 8 chữ số.
- `DIVIDEND`: cash tăng `gross - fee - tax`, ghi vào thu nhập ròng.
- `realized_pnl = tiền bán ròng - cost basis đã bán`.
- `unrealized_pnl = market value - cost basis còn lại`.
- `total_pnl = realized_pnl + unrealized_pnl + net_income`.
- `portfolio_value = cash + securities_value`; `total_assets` cộng thêm tài sản thủ công.
- Goal chỉ là tiến độ kế hoạch, không giữ tiền và không cộng vào tài sản.
- Nếu một vị thế thiếu giá hợp lệ, tổng định giá chính chuyển thành `null`, không âm thầm đổi thành 0.

## 3. Ghi nạp/rút và ghi nhận BUY/SELL

Form `Transactions` gọi `RecordTransaction` nhưng service này chỉ nhận `DEPOSIT` và `WITHDRAW`. BUY/SELL đi qua `PaperTradingService` và chỉ được tạo bởi execution của paper order.

### Nạp/rút tiền

1. FormRequest chỉ nhận field người dùng được phép nhập.
2. Server tự đặt `trade_date`, `cash_delta`, `gross_amount` và `request_hash`.
3. Kiểm tra portfolio thuộc user và cash sau giao dịch không âm.
4. `request_key` duy nhất theo portfolio để retry mạng không tạo giao dịch trùng.
5. Cùng key cùng payload trả lại receipt cũ; cùng key khác payload trả `409`.
6. Lỗi validation trả `422`; không ghi một phần dữ liệu.
7. Transaction đã ghi là immutable trong bản demo.

### Paper order BUY/SELL

1. Authorize portfolio, khóa instrument rồi khóa portfolio.
2. Kiểm tra request key/hash, phiên mô phỏng và quote hiện tại.
3. Tính available cash/quantity sau các reservation đang mở.
4. Tạo order và reservation; Market order có thể match ngay với Ask/Bid hiện tại.
5. Mỗi fill tạo một execution và một transaction BUY/SELL immutable.
6. Partial fill giữ phần còn lại; filled/cancelled giải phóng reservation.
7. Lỗi ở bất kỳ bước nào rollback cả order, reservation, execution và transaction.

## 4. API và quyền riêng tư

- API private yêu cầu session/auth và policy owner.
- Portfolio, goal, task, asset, watchlist, alert và notification đều lọc theo `user_id`.
- Truy cập bản ghi của user khác trả `404` để không tiết lộ bản ghi tồn tại.
- Public Data API của Supabase bị hạn chế; Laravel là nơi duy nhất dùng credential database.
- API giá Binance chỉ nhận allowlist `BTCUSDT`, `ETHUSDT`, `SOLUSDT`, giới hạn 7/30/90 ngày, timeout 3/5 giây và cache 60 giây.
- Giá Binance chỉ hiển thị tham khảo, không tham gia tính tài sản danh mục demo.

## 5. Cảnh báo và dữ liệu học

- Alert chỉ tạo notification khi điều kiện chuyển từ false sang true.
- Điều kiện true lặp lại không tạo notification trùng; khi false rồi true thì được kích hoạt lại.
- Không có giá hợp lệ thì không đánh giá alert.
- Lesson slug nằm trong allowlist và tiến độ có unique `(user_id, lesson_slug)`.
- Copilot, strategy và simulation là deterministic/mock; không tự nhận là AI, backtest hay khuyến nghị đầu tư thật.

## 6. Checklist nghiệm thu

1. Đăng nhập demo A và B, xác nhận dữ liệu không lẫn.
2. Deposit/withdraw tại Transactions; BUY/SELL tại Market; reload và đối chiếu cash/quantity/P&L.
3. Retry cùng `request_key`, thử lệnh SELL vượt quantity và rút vượt cash.
4. Tạo/sửa/xóa goal, asset, task, watchlist và alert.
5. Tải giá thật Binance và kiểm tra trạng thái lỗi khi nguồn không phản hồi.
6. Kiểm tra màn hình 360px, 768px và desktop.
7. Chạy PHPUnit SQLite, PostgreSQL concurrency test trên database disposable, TypeScript và Vite build.
8. Đối chiếu ma trận T01-T14 trong `ACCEPTANCE_REPORT.md` trước khi tạo bản release/demo presentation.

## Admin · cập nhật triển khai

- `/admin` yêu cầu đăng nhập và `role=admin`; user thường nhận 403.
- Trang quản trị hiện chỉ đọc: tài khoản phân trang, mã tài sản và số bản ghi giá. Chưa có khóa user, sửa giá hoặc audit log.
- Role không nằm trong fillable của User; register/settings không nhận quyền từ client.
- `AdminSeeder` chạy riêng với `DEMO_ENABLED=true`, `DEMO_ADMIN_PASSWORD` tối thiểu 16 ký tự, khác mật khẩu user demo và database.
- Seeder không nâng quyền tài khoản thường có cùng email và không đổi mật khẩu admin đã tồn tại.
- Đã áp dụng migration role và tạo admin trên DB demo. Thông tin đăng nhập nằm trong `mofi-app/.local-demo-credentials.md` bị Git ignore.
- Admin dùng `/login` rồi mở `/admin`; không dùng tài khoản quản trị để trình diễn giao dịch.

## 7. Paper market và đồng hồ mô phỏng

- `QuoteBoardService` tạo session theo instrument/ngày, seed 54 tick cho hai phiên 09:00-11:30 và 13:00-14:55, mỗi tick cách 5 phút mô phỏng.
- Mỗi request board khóa session và chỉ tiến một tick khi đủ `real_interval_seconds` (mặc định 5 giây); frontend polling là tín hiệu để server tiến tick, không phải timer nền độc lập. Khi đủ 54 tick, session đóng, lệnh OPEN/PARTIALLY_FILLED hết hạn và session ngày làm việc kế tiếp bắt đầu tại tick 0.
- Tick lưu last/reference/ceiling/floor, bid1-3, ask1-3, quantity và total volume. Đây là thanh khoản mô phỏng, không phải sổ lệnh của sàn.
- `MarketMatchingService` khớp BUY theo Ask và SELL theo Bid, đi qua tối đa ba depth level; phần chưa khớp giữ ở `PARTIALLY_FILLED`.
- `ReplayController::candles` trả OHLC ngày deterministic từ `DemoReplayProvider`; endpoint board private yêu cầu auth.
