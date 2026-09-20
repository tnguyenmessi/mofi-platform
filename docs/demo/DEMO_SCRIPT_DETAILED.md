# MOFI — Kịch bản demo chi tiết

Tài liệu này dùng cho buổi demo nội bộ. Toàn bộ tiền, giá cổ phiếu và lệnh mua bán trong MOFI là mô phỏng.

## 0. Chuẩn bị

Local:

```powershell
cd "C:\Users\nguye\Downloads\DỰ ÁN 2\mofi-app"
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

Mở `http://127.0.0.1:8000/`. Tài khoản nằm trong `mofi-app/.local-demo-credentials.md`; không chiếu hoặc đưa file này vào Git.

| Tài khoản | Mục đích |
| --- | --- |
| Demo A | Dashboard có fixture, danh mục, mục tiêu và lịch sử |
| Demo B | Portfolio riêng để thử nạp/rút và paper order |
| Admin | Trang quản trị và health check |

Nếu dùng Railway, chỉ trình bày read-only khi phiên đã đóng. Không gửi thao tác tài chính lên live nếu chưa xác nhận ngay trước thao tác.

## 1. Landing — 1 phút

1. Mở `/`.
2. Giới thiệu MOFI là nền tảng tài chính cá nhân và đầu tư thực hành.
3. Chỉ vào nhãn tiền ảo/giá mô phỏng và nói rõ ảnh tham chiếu chỉ dùng cho bố cục.

## 2. Đăng nhập và dashboard — 2 phút

1. Đăng nhập Demo A.
2. Chỉ vào `Tổng tài sản`, `Tiền mặt khả dụng`, `Tiền đang giữ`, `Danh mục đầu tư`, `Lãi/lỗ tổng` và `Lệnh chờ`.
3. Cuộn qua allocation, goals, market, Copilot, portfolio chart, watchlist, alerts và tasks.
4. Giải thích: summary do Laravel tính từ ledger + giá mô phỏng; React/Inertia không quyết định số dư cuối cùng.

## 3. Thị trường và bảng giá — 2 phút

1. Sidebar → `Thị trường`.
2. Chọn `MOFI`.
3. Chỉ vào giá khớp cuối, tham chiếu, trần, sàn, khối lượng khớp/tổng.
4. Chỉ vào `Ask 1-3`, `Bid 1-3`, trạng thái phiên, giờ mô phỏng và tick.
5. Nói rõ: trình duyệt polling mỗi 2 giây, nhưng server chỉ tăng một tick sau đủ 5 giây thật; mỗi tick tương đương 5 phút mô phỏng. Hết 54 tick, hệ thống tự chuyển sang ngày làm việc kế tiếp.
6. Chỉ vào nến OHLC ngày, volume và badge `Mô phỏng` nếu đang hiển thị.
7. Panel Binance là crypto tham khảo bằng USDT, không nhập vào danh mục VND.

## 4. Paper trading — 3 phút

Chỉ làm thao tác ghi khi dùng local hoặc khi đã có xác nhận trực tiếp cho môi trường live.

1. Chọn `Mua` hoặc `Bán`.
2. Chọn `LO · Lệnh giới hạn` hoặc `MP · Lệnh thị trường`.
3. Với LO BUY, đặt thấp hơn Ask 1 để thấy `OPEN`; với LO SELL, đặt cao hơn Bid 1 để thấy `OPEN`.
4. Chọn MP hoặc đặt limit đủ điều kiện để lệnh khớp.
5. Chỉ vào số lượng đặt/khớp, trạng thái, reservation và số lần execution.
6. Đặt khối lượng lớn hơn một depth level để minh họa `PARTIALLY_FILLED`.
7. Hủy lệnh đang `OPEN/PARTIALLY_FILLED`; kiểm tra tiền hoặc quantity khả dụng được giải phóng.
8. Reload trang; order/history vẫn giữ và tick đã xử lý không chạy lại.

Giải thích: BUY ăn Ask, SELL ăn Bid; server lock portfolio/order và ghi execution + transaction trong một database transaction.

## 5. Nạp / rút tiền và đối soát — 2 phút

1. Sidebar → `Nạp / rút tiền`.
2. Dùng Demo B nếu cần thay đổi dữ liệu trình bày.
3. Chọn `Nạp tiền ảo` hoặc `Rút tiền ảo`, nhập số tiền.
4. Chỉ vào vùng preview, số dư dự kiến và receipt.
5. Bấm `Xác nhận nạp / rút mô phỏng`.
6. Trong lịch sử, lọc `Nạp tiền ảo`/`Rút tiền ảo`; các dòng BUY/SELL đã khớp vẫn hiển thị để đối soát.

Lưu ý: form này không có lựa chọn Mua/Bán. BUY/SELL chỉ được tạo qua order trong Market.

## 6. Kiểm thử lỗi an toàn — 1 phút

1. Thử rút lớn hơn tiền khả dụng → server trả lỗi, không thêm ledger.
2. Vào Market, thử bán lớn hơn quantity khả dụng → order bị từ chối.
3. Dùng lại cùng request key → trả receipt/order cũ; payload khác cùng key → `409`.

## 7. Các module khác — 2 phút

- `Tiền & tài sản`: cash, holdings và manual assets.
- `Mục tiêu tài chính`: target/saved/deadline; saved không trừ cash.
- `Danh sách theo dõi`: unique theo user + instrument.
- `Cảnh báo & thông báo`: false → true mới tạo notification; true lặp không spam.
- `Việc cần làm`, `Học đầu tư`: trạng thái lưu theo user.
- `MOFI Copilot`: rule-based, read-only, có disclaimer.
- `Chiến lược`, `Phòng mô phỏng`: fixture/what-if, không phải backtest và không sửa ledger.
- `Cộng đồng`: bài mô phỏng, owner mới được xóa bài của mình.

## 8. Admin — 1 phút

1. Đăng xuất.
2. Đăng nhập tài khoản Admin local.
3. Mở `/admin` và health check.
4. Chỉ vào trạng thái database/cache/catalog/demo prices.
5. Giải thích role middleware; user thường không vào được admin.

## 9. Câu kết

> “MOFI là modular monolith Laravel + React/Inertia + PostgreSQL/Supabase. Luồng paper trading khép kín từ bảng giá mô phỏng đến order, reservation, execution, ledger và portfolio summary. Bản demo chưa kết nối broker, tiền thật, dữ liệu exchange realtime hoặc LLM trả phí.”

## 10. Xử lý sự cố

- Trang local không mở: kiểm tra `php artisan serve` và `npm run dev`.
- Login lỗi: lấy credential từ file local, không đoán mật khẩu.
- Market chậm hoặc 429: chờ polling, không bấm liên tục.
- Hết ngày: chờ hệ thống tự mở ngày làm việc kế tiếp ở tick `0/54`; lệnh chưa khớp ngày trước bị hủy và reservation được trả lại.
- Limit chưa khớp: giải thích điều kiện giá, chuyển tick ở local hoặc hủy lệnh.
- Binance lỗi: bỏ qua panel crypto, tiếp tục phần MOFI demo.
- Asset runtime warning: chạy `npm run build`, kiểm tra lại đường dẫn ảnh; không coi đây là lỗi nghiệp vụ giao dịch.
