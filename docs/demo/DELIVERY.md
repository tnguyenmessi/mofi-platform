# Bàn giao và nghiệm thu bản demo MOFI

## 1. Phạm vi đã bàn giao

- Laravel 13 + React 19 + Inertia + TypeScript + PostgreSQL/Supabase.
- Bảng giá chứng khoán mô phỏng có giá khớp cuối, tham chiếu, trần/sàn, khối lượng, bid/ask và ngày/giờ mô phỏng.
- Nhịp replay mặc định: 5 giây thật = 5 phút mô phỏng; ngày mô phỏng cố định `15/09/2026`.
- `Nạp / rút tiền` chỉ ghi `DEPOSIT/WITHDRAW`.
- Mua/bán đi qua `Market`: order Market/Limit, reservation, execution, partial fill, cancel và transaction BUY/SELL immutable sau khi khớp.
- Không có broker, ngân hàng, thanh toán, KYC, tiền thật hay dữ liệu chứng khoán Việt Nam realtime.

## 2. Luồng trình bày khoảng bảy phút

1. Mở landing và nói rõ toàn bộ tiền, giá và lệnh là mô phỏng.
2. Đăng nhập Demo A, chỉ ra dashboard lấy số liệu từ cùng một portfolio summary.
3. Mở `Thị trường`, chọn `MOFI`, chỉ vào giá khớp, bid/ask, volume, phiên và đồng hồ mô phỏng.
4. Khi phiên local còn mở, đặt một lệnh `LO` dưới Ask để giữ `OPEN`, hoặc `MP` để minh họa khớp theo depth. Giải thích reservation và partial fill.
5. Hủy một lệnh đang mở để chứng minh tiền/số lượng được giải phóng.
6. Mở `Nạp / rút tiền`: chỉ chọn nạp hoặc rút, xem preview/receipt; mở lịch sử để đối soát các execution BUY/SELL đã khớp.
7. Kết thúc bằng ownership, goals, watchlist, alerts, Copilot, simulation và các giới hạn production.

Không gửi thao tác tài chính lên URL live dùng chung nếu chưa có xác nhận ngay trước thao tác. Phiên Railway hiện đã đóng ở tick `54/54`, nên chỉ đọc và trình bày trạng thái.

## 3. Ma trận nghiệm thu

| Test | Tình huống | Kết quả |
| --- | --- | --- |
| T01 | Menu public/private, auth redirect, 404 | Đạt; live smoke và feature tests |
| T02 | Register/login/logout, sai mật khẩu, hash/session | Đạt; `WorkspaceTest` và auth smoke |
| T03 | User A truy cập dữ liệu User B | Đạt; policy, owner query và 404 |
| T04 | Cash, holdings, basis, P/L, total assets | Đạt; fixture `W = 38.315.000 VND` |
| T05 | Nạp/rút và order paper BUY/SELL | Đạt; Transactions chỉ nạp/rút, Market tạo order |
| T06 | Rút thiếu tiền, bán quá lượng, fee/tax sai | Đạt; 422 và rollback |
| T07 | Retry/idempotency/concurrency | Đạt; PostgreSQL disposable local `1 test / 18 assertions` ở cả native và emulated prepares |
| T08 | Goal progress và reload | Đạt |
| T09 | Watchlist/task/lesson idempotency | Đạt |
| T10 | Alert false→true, true lặp, re-arm | Đạt |
| T11 | Copilot/strategy/simulation mock boundary | Đạt |
| T12 | Missing quote, error state, responsive | Đạt một phần: logic missing/error và snapshot tablet đạt; cần runner cố định để chốt 360/768/1366 |
| T13 | Test/build/secret scan | Đạt: PHPUnit `66 / 65 passed / 1 skipped / 935 assertions`, TypeScript, Vite |
| T14 | Permissions, deploy, debug exposure | Đạt trong smoke public Railway; production monitoring và cấu hình vận hành vẫn là gate riêng |

Chi tiết bằng chứng nằm trong [`ACCEPTANCE_REPORT.md`](ACCEPTANCE_REPORT.md).

## 4. Công thức và quyết định nghiệp vụ

- `cash = tổng cash_delta` của ledger.
- BUY/SELL không được tạo từ form Transactions; chỉ execution của order mới tạo dòng BUY/SELL.
- BUY giữ `gross` dự kiến; SELL giữ quantity. Cancel/reject/filled giải phóng phần reservation phù hợp.
- Market BUY ăn Ask, Market SELL ăn Bid; Limit BUY khớp khi Ask <= giá đặt; Limit SELL khớp khi Bid >= giá đặt.
- Lệnh lớn có thể khớp qua nhiều depth level và còn lại `PARTIALLY_FILLED`.
- Một request key + payload chỉ có một order/receipt; key khác payload trả conflict.
- Mọi thay đổi tài chính nằm trong database transaction và lock portfolio/order.

## 5. Kiểm thử đã chạy

```powershell
cd mofi-app
php -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/bin/phpunit --colors=never
npx tsc --noEmit
npm run build
git diff --check
```

PostgreSQL concurrency là test opt-in, chỉ chạy với database disposable local `mofi_transaction_test` trên `127.0.0.1:55439`; không chạy trên Railway/Supabase.

## 6. Hướng dẫn local

```powershell
cd "C:\Users\nguye\Downloads\DỰ ÁN 2\mofi-app"
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
```

Tài khoản demo nằm trong file local bị Git ignore; không đưa credential vào tài liệu hoặc màn hình trình bày.
