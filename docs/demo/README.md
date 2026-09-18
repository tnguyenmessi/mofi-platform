# MOFI Demo 2 ngày

Phiên bản kế hoạch 1.0, ngày 17/09/2026. Bộ tài liệu này là nguồn triển khai cho bản demo đánh giá năng lực theo hai ảnh tham chiếu. Đây là đặc tả công việc sắp làm, không phải báo cáo các chức năng đã hoàn thành hay phê duyệt của sếp.

## Tài liệu có hiệu lực

1. [Phạm vi và chức năng](SPECIFICATION.md): màn hình, thao tác, use case và quy tắc.
2. [Database demo](DATABASE.md): ERD, bảng/cột, ràng buộc, công thức và dữ liệu mẫu.
3. [Bàn giao và nghiệm thu](DELIVERY.md): Git, lịch hai ngày, test và kịch bản trình bày.

Hai file Word trong `docs/word/` là bản trình bày của nội dung này. Nội dung Markdown là nguồn cập nhật. Các tài liệu v0.1 ở thư mục `docs/` trước đây mô tả sản phẩm dài hạn; nếu khác nhau, ưu tiên bộ `docs/demo/` cho lần triển khai này. Không triển khai mô hình ledger nhiều tài khoản, reversal/rebuild revision hoặc nhiều tầng job chỉ để phục vụ demo.

## Quyết định để bắt đầu

- Giữ đầy đủ các khu vực trên landing và dashboard; mọi menu có trang hoặc trạng thái rõ ràng.
- Dữ liệu người dùng, tiền, cổ phiếu, thị trường và cộng đồng đều là giả. Đăng nhập, lưu DB và tính danh mục là chức năng thật.
- Laravel 13 + React 19 + TypeScript + Inertia + Tailwind; Recharts cho biểu đồ; PostgreSQL trên Supabase.
- Một portfolio VND mỗi user; cash tính từ lịch sử giao dịch; không nợ/margin/FX/chuyển nhiều account.
- Giá theo bộ dữ liệu ngày mô phỏng cố định. Không mua API hoặc triển khai giao dịch tiền thật.
- AI, Strategy Studio, Investment Lab và cộng đồng là demo có giới hạn, không phải dịch vụ sản xuất đã hoàn chỉnh.
- Có thể bắt đầu code nền sau tài liệu; không chờ phê duyệt tất cả quy tắc của sản phẩm tài chính dài hạn. Nếu yêu cầu mới mở rộng scope, đổi bảng phạm vi trước khi code.

## Trạng thái đã kiểm tra

Ngày 18/09/2026: kết nối Laravel qua PDO PostgreSQL/TLS đã chạy, migration demo và các migration quyền/RLS đã áp dụng. Demo seed, browser QA, PHPUnit, TypeScript và production build đã được kiểm tra. Credential chỉ nằm trong `.env` local bị Git ignore.

Repository có workflow CI cho Pint/PHPUnit/TypeScript/Vite build. Ứng dụng chưa có hosting production; bản demo chạy local/Supabase và không triển khai giao dịch tiền thật.
