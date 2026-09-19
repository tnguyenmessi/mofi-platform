# MOFI - Kịch bản demo chi tiết

Tài liệu này dùng cho buổi demo nội bộ. Toàn bộ tiền, giá cổ phiếu và lệnh mua bán trong MOFI là mô phỏng.

## 0. Chuẩn bị

Mở hai terminal:

```powershell
cd "C:\Users\nguye\Downloads\DỰ ÁN 2\mofi-app"
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal thứ hai:

```powershell
cd "C:\Users\nguye\Downloads\DỰ ÁN 2\mofi-app"
npm run dev
```

Mở `http://127.0.0.1:8000/`.

Tài khoản và mật khẩu nằm trong `mofi-app/.local-demo-credentials.md`. File đã bị Git ignore; mở riêng trên máy, không chiếu lên màn hình và không đưa vào GitHub.

| Tài khoản | Mục đích |
|---|---|
| Demo A | Dashboard có sẵn dữ liệu, mục tiêu, giao dịch và danh mục |
| Demo B | Tài khoản trống để thử nạp tiền và lệnh mô phỏng |
| Admin | Trang quản trị và health check |

## 1. Landing - 1 phút

1. Chỉ vào logo MOFI và nói: “Đây là nền tảng quản lý tài chính cá nhân và đầu tư thực hành.”
2. Cuộn qua hero, nhóm tính năng và market preview.
3. Bấm `Đăng nhập`.

Nói rõ: bố cục lấy cảm hứng từ ảnh mẫu; giá chứng khoán trong bản demo có nhãn mô phỏng.

## 2. Đăng nhập và dashboard - 2 phút

1. Nhập email Demo A trong file credential local.
2. Nhập mật khẩu tương ứng trong file credential local.
3. Bấm `Đăng nhập`.
4. Lần lượt chỉ vào `Tổng tài sản`, `Tiền mặt khả dụng`, `Tiền đang giữ`, `Danh mục đầu tư`, `Lãi/lỗ tổng`, `Lệnh chờ`.
5. Cuộn xuống chỉ vào `Phân bổ tài sản`, `Mục tiêu tài chính`, `Thị trường mô phỏng`, Copilot, biểu đồ danh mục, watchlist, cảnh báo và việc cần làm.

Giải thích: “Laravel tính summary từ ledger giao dịch, giá mô phỏng và vị thế ở server; React/Inertia chỉ hiển thị.”

## 3. Thị trường và biểu đồ - 2 phút

1. Sidebar → `Thị trường`.
2. Ô tìm kiếm → nhập `MOFI`.
3. Chỉ vào badge `Mô phỏng`.
4. Bấm lần lượt `7 ngày`, `14 ngày`, `30 ngày`.
5. Chỉ vào biểu đồ nến, volume và `Sổ lệnh mô phỏng`.
6. Chỉ vào form `Paper trading · Bảng lệnh mô phỏng`.
7. Nếu cần, chọn `BTCUSDT` ở phần crypto và bấm `Tải giá thị trường`.

Giải thích: cổ phiếu Việt Nam dùng provider replay cố định; crypto Binance là nguồn tham khảo riêng, không trộn vào danh mục VND.

## 4. Paper trading - đặt lệnh và replay - 2 phút

1. Trong form, chọn mã `MOFI`, chiều `Mua`, loại `Limit`.
2. Nhập số lượng `10`, giá giới hạn `124000`.
3. Bấm `Đặt lệnh mô phỏng`.
4. Chỉ vào thông báo lệnh đang chờ, dashboard `Tiền đang giữ` và `Lệnh chờ`.
5. Bấm `Tiến phiên 1`; nếu chưa đạt giá, bấm phiên tiếp theo.
6. Chỉ vào thông báo số lệnh khớp.
7. Reload trang và chỉ ra nút đang ở phiên kế tiếp, không chạy lại tick cũ.
8. Nếu cần, bấm `Hủy lệnh` để chứng minh reservation được giải phóng.

Giải thích: server khóa portfolio, giữ tiền hoặc số lượng, kiểm tra điều kiện limit, rồi ghi execution và transaction trong cùng database transaction.

## 5. Giao dịch và receipt - 2 phút

1. Sidebar → `Giao dịch`.
2. Dùng Demo B để tránh làm thay đổi dữ liệu trình bày của Demo A.
3. Chọn `Nạp tiền ảo`, nhập `2000000`.
4. Quan sát vùng `Xem trước giao dịch`.
5. Bấm `Ghi giao dịch mô phỏng`.
6. Chỉ vào receipt: mã, ngày, giá trị, phí/thuế và dòng tiền.
7. Cuộn xuống lịch sử, dùng bộ lọc loại, mã hoặc ngày.
8. Bấm `Xuất CSV đã lọc` nếu cần.

Giải thích: preview chỉ là ước tính; server xác thực số tiền. request key + request hash chống ghi trùng khi bấm lại.

## 6. Kiểm thử lỗi an toàn - 1 phút

1. Chọn `Rút tiền ảo`, nhập số lớn hơn số dư, bấm ghi.
2. Chỉ vào lỗi validation và xác nhận lịch sử không có dòng mới.
3. Nếu có vị thế, chọn `Bán` lớn hơn lượng nắm giữ.
4. Chỉ vào lỗi tương tự.

Giải thích: validation xảy ra trước insert; transaction rollback nên không có số dư âm hoặc bản ghi dở dang.

## 7. Các module khác - 2 phút

Bấm lần lượt trên sidebar: `Tiền & tài sản`, `Danh mục đầu tư`, `Mục tiêu tài chính`, `Danh sách theo dõi`, `Cảnh báo & thông báo`, `Việc cần làm`, `MOFI Copilot`, `Chiến lược`, `Phòng mô phỏng`, `Học đầu tư`, `Cộng đồng`.

Điểm cần chỉ:
- Goal có tiến độ, deadline và số tiền cần thêm mỗi tháng.
- Alert có điều kiện giá và notification.
- Simulation chỉ lưu kịch bản, không thay đổi ledger.
- Copilot là rule-based, read-only và có disclaimer.
- Dữ liệu workspace được giới hạn theo user.

## 8. Admin - 1 phút

1. Bấm `Đăng xuất`.
2. Vào `/login`, nhập tài khoản Admin từ file local.
3. Mở `/admin`.
4. Bấm `Kiểm tra hệ thống`.
5. Chỉ vào bốn trạng thái: Database, Cache, Danh mục, Giá mô phỏng đều `Sẵn sàng`.
6. Chỉ vào danh sách tài khoản, danh mục mã và audit log.

Giải thích: route admin có role middleware; tài khoản thường bị từ chối. Credential không nằm trong Git hoặc frontend bundle.

## 9. Câu kết với sếp

> “MOFI là modular monolith Laravel + React/Inertia + PostgreSQL/Supabase. Luồng chính khép kín từ market data mô phỏng → order → reservation → execution → transaction → portfolio summary → dashboard. Bản demo không dùng tiền thật, chưa kết nối broker và Copilot hiện là rule-based. Provider giá thật, LLM thật, CI/CD và monitoring là các bước mở rộng tiếp theo.”

## 10. Xử lý sự cố

- Không mở trang: kiểm tra hai terminal và dùng đúng `http://127.0.0.1:8000`.
- Login lỗi: lấy lại thông tin từ file local, không tự đoán mật khẩu.
- Trang market chậm: chờ request đầu tiên, không bấm liên tục để tránh `429`.
- Vite asset lỗi: chạy `npm run build` rồi reload.
- Binance lỗi: bỏ qua phần crypto live, tiếp tục chart MOFI mô phỏng.
- Limit chưa khớp: nói rõ giá chưa đạt điều kiện, bấm phiên tiếp theo hoặc hủy lệnh.
- Cần màn hình sạch: đăng xuất và dùng Demo A cho phần read-only.

