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
- Laravel + React + TypeScript + Inertia + Tailwind; Recharts cho biểu đồ; PostgreSQL trên Supabase. React/Inertia chưa được cài ở thời điểm viết tài liệu.
- Một portfolio VND mỗi user; cash tính từ lịch sử giao dịch; không nợ/margin/FX/chuyển nhiều account.
- Giá theo bộ dữ liệu ngày mô phỏng cố định. Không mua API hoặc triển khai giao dịch tiền thật.
- AI, Strategy Studio, Investment Lab và cộng đồng là demo có giới hạn, không phải dịch vụ sản xuất đã hoàn chỉnh.
- Có thể bắt đầu code nền sau tài liệu; không chờ phê duyệt tất cả quy tắc của sản phẩm tài chính dài hạn. Nếu yêu cầu mới mở rộng scope, đổi bảng phạm vi trước khi code.

## Trạng thái đã kiểm tra

Ngày 17/09/2026: mật khẩu đã được đặt vào `.env` local bị Git ignore. Kết nối PDO PostgreSQL bằng thông số local và TLS `require` thành công; có 0 bảng trong schema `public`. Chưa boot/test query thông qua Laravel application, chưa kiểm tra toàn bộ grants/Data API, chưa chạy migration và chưa có bảng demo. Không có credential trong tài liệu.

Repository có Laravel skeleton và templates issue/PR; chưa có CI workflow, giao diện MOFI, demo seed hoặc hosting demo. Các trạng thái này chỉ đổi khi có bằng chứng kiểm tra tương ứng, không đổi vì đã viết tài liệu.
