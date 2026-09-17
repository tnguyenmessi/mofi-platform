# Kịch bản demo MOFI trong 7 phút

1. Mở landing, giới thiệu Laravel, React, PostgreSQL/Supabase và dữ liệu demo.
2. Đăng nhập Demo A, chỉ ra bốn chỉ số, donut, mục tiêu, bảng thị trường, biểu đồ, watchlist, cảnh báo và task.
3. Mở Thị trường: giá cổ phiếu Việt Nam là mô phỏng; BTC/ETH/SOL có nút tải giá công khai Binance.
4. Mở Giao dịch, nạp hoặc mua ảo, reload và đối chiếu cash, số lượng, P&L, biểu đồ.
5. Thử bán vượt số lượng hoặc gửi trùng mã yêu cầu để trình bày validation và idempotency.
6. Tạo/sửa goal, tài sản, task, watchlist, alert; reload để chứng minh lưu theo user.
7. Đăng xuất, vào Demo B để chứng minh dữ liệu A không bị lộ.
8. Mở Copilot, Simulation, Learn và nói rõ đây là phép tính deterministic/nội dung mẫu.
9. Kết thúc bằng `docs/demo/BACKEND_LOGIC.md`, `DATABASE.md` và migration.

## Fixture A

- Cash `14.565.000 ₫`, chứng khoán `18.750.000 ₫`, tài sản thủ công `5.000.000 ₫`
- Tổng tài sản `38.315.000 ₫`, lãi/lỗ tổng `3.315.000 ₫`
- MOFI `150` cổ phiếu, giá mô phỏng `125.000 ₫`

## Lệnh kiểm tra

```powershell
php vendor/bin/pint --dirty --format agent
php -d extension=pdo_sqlite vendor/bin/phpunit --colors=never
$env:MOFI_PG_TEST='1'; php vendor/bin/phpunit tests/Feature/PostgresTransactionConcurrencyTest.php --colors=never; Remove-Item Env:MOFI_PG_TEST
npm run build
```
