# MOFI Demo — Bộ tài liệu hiện hành

Bộ này mô tả bản demo đã triển khai, không phải bản đặc tả dài hạn của một công ty chứng khoán.

## Đọc theo thứ tự

1. [`SPECIFICATION.md`](SPECIFICATION.md) — phạm vi, công nghệ, màn hình và use case.
2. [`DATABASE.md`](DATABASE.md) — bảng dữ liệu, công thức, order/reservation/execution và replay.
3. [`BACKEND_LOGIC.md`](BACKEND_LOGIC.md) — service flow, quyền, matching và đồng hồ mô phỏng.
4. [`DEMO_RUNBOOK.md`](DEMO_RUNBOOK.md) — lời thoại và thuật toán để trình bày.
5. [`DEMO_SCRIPT_DETAILED.md`](DEMO_SCRIPT_DETAILED.md) — từng bước bấm trên giao diện.
6. [`ACCEPTANCE_REPORT.md`](ACCEPTANCE_REPORT.md) — bằng chứng T01-T14 và giới hạn còn lại.
7. [`NEXT_ROADMAP.md`](NEXT_ROADMAP.md) — hướng mở rộng sau demo.

## Quyết định nghiệp vụ quan trọng

- `Nạp / rút tiền` chỉ tạo `DEPOSIT/WITHDRAW`.
- `Mua/Bán` chỉ tạo paper order tại `Thị trường`; execution khớp mới sinh ledger `BUY/SELL`.
- Bảng giá có last/reference/ceiling/floor, bid/ask depth, volume, ngày/giờ mô phỏng và tick tự cập nhật.
- Mặc định 5 giây thật = 5 phút mô phỏng; ngày cố định `2026-09-15`.
- Giá cổ phiếu Việt Nam là fixture/replay có nhãn; Binance là panel crypto tham khảo tách biệt.
- Không có broker, tiền thật, KYC, thanh toán hay dữ liệu exchange realtime.

Word/PDF gửi quản lý nằm ở `docs/word/` và `docs/pdf/`.
