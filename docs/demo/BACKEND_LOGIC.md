# MOFI Backend Logic

Tài liệu này mô tả các quy tắc backend cần giữ khi mở rộng dự án. Dữ liệu demo là tiền ảo và giá mô phỏng, không đặt lệnh tài chính thật.

## 1. Kiến trúc xử lý

`Browser -> Laravel route/middleware -> FormRequest/policy -> Controller -> Service -> PostgreSQL/Supabase -> JSON/Inertia response`.

- Laravel quản lý session, CSRF, rate limit, validation, authorization và truy vấn database.
- React/Inertia chỉ hiển thị dữ liệu; browser không kết nối trực tiếp Supabase.
- Tiền, giá, phí, thuế và giá vốn dùng `numeric`/`BigDecimal`; JSON trả số tiền dạng chuỗi.
- Ngày mô phỏng cố định ở `config/demo.php`; không dùng ngày hiện tại để thay đổi fixture.

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

## 3. Ghi giao dịch

`RecordTransaction` chạy trong transaction database và khóa dòng portfolio bằng `lockForUpdate()`.

1. FormRequest chỉ nhận field người dùng được phép nhập.
2. Server tự đặt `trade_date`, `cash_delta`, `gross_amount` của giao dịch mua/bán và `request_hash`.
3. Kiểm tra portfolio thuộc user, instrument có thể giao dịch và đúng thị trường VN/VND.
4. Kiểm tra đủ cash hoặc đủ quantity trước khi ghi.
5. `request_key` duy nhất theo portfolio để retry mạng không tạo giao dịch trùng.
6. Cùng key cùng payload trả lại receipt cũ; cùng key khác payload trả `409`.
7. Lỗi validation trả `422`; không ghi một phần dữ liệu.
8. Transaction đã ghi là immutable trong bản demo.

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

## 6. Checklist nghiệm thu tiếp theo

1. Đăng nhập demo A và B, xác nhận dữ liệu không lẫn.
2. Deposit -> BUY -> SELL, reload và đối chiếu cash/quantity/P&L.
3. Retry cùng `request_key`, thử SELL vượt quantity.
4. Tạo/sửa/xóa goal, asset, task, watchlist và alert.
5. Tải giá thật Binance và kiểm tra trạng thái lỗi khi nguồn không phản hồi.
6. Kiểm tra màn hình 360px, 768px và desktop.
7. Chạy Pint, PHPUnit SQLite, PostgreSQL concurrency test và Vite build.
8. Chỉ sau khi checklist đạt mới tạo bản release/demo presentation.

## Admin · cập nhật triển khai

- `/admin` yêu cầu đăng nhập và `role=admin`; user thường nhận 403.
- Trang quản trị hiện chỉ đọc: tài khoản phân trang, mã tài sản và số bản ghi giá. Chưa có khóa user, sửa giá hoặc audit log.
- Role không nằm trong fillable của User; register/settings không nhận quyền từ client.
- `AdminSeeder` chạy riêng với `DEMO_ENABLED=true`, `DEMO_ADMIN_PASSWORD` tối thiểu 16 ký tự, khác mật khẩu user demo và database.
- Seeder không nâng quyền tài khoản thường có cùng email và không đổi mật khẩu admin đã tồn tại.
- Đã áp dụng migration role và tạo admin trên DB demo. Thông tin đăng nhập nằm trong `mofi-app/.local-demo-credentials.md` bị Git ignore.
- Admin dùng `/login` rồi mở `/admin`; không dùng tài khoản quản trị để trình diễn giao dịch.
