# Kế hoạch nâng cấp MOFI sau bản demo

## Kết luận định hướng

- Tập trung desktop web trước. Mobile chỉ giữ responsive cơ bản để không vỡ layout.
- Tiếp tục dùng giá chứng khoán Việt Nam mô phỏng trong giai đoạn này. Gắn `source=demo`, `is_demo=true`, ngày dữ liệu cố định và không gọi API không ổn định.
- Giữ tiền ảo, giao dịch và danh mục như hiện tại; không kết nối ngân hàng, môi giới hoặc thanh toán thật.
- Chưa cần trang admin để hoàn thành bản demo. Nên thêm nền tảng role `user/admin` để sẵn sàng mở rộng.

## Mục tiêu đợt tiếp theo

### P0 · Hoàn thiện trải nghiệm demo

1. Chốt layout desktop 1366px theo ảnh: sidebar cố định, header tìm kiếm, hero, 4 metric, 3 panel dữ liệu, Copilot + biểu đồ, watchlist/cảnh báo/task, 4 tile cuối.
2. Bổ sung icon, ảnh đại diện, trạng thái loading/empty/error và tooltip cho từng biểu đồ.
3. Tạo màn hình giao dịch dễ trình bày: xem số dư trước khi gửi, xem preview cash/quantity sau giao dịch, thông báo lỗi ngay cạnh field.
4. Thêm bảng lịch sử có bộ lọc `kind`, mã tài sản, ngày và phân trang.
5. Hoàn thiện seeded fixture để mỗi lần reset database có cùng watchlist, task, alert và lesson progress.
6. Tạo script smoke test cho luồng trình bày và ghi kết quả vào `DELIVERY.md`.

### P1 · Chức năng sản phẩm thực tế hơn

1. `/assets`: sửa/xóa tài sản thủ công, nhóm tài sản và lịch sử định giá.
2. `/goals`: deadline, số tiền cần mỗi tháng, trạng thái quá hạn và biểu đồ tiến độ.
3. `/watchlist`: tìm mã, lọc thị trường, xem mini chart 30 ngày, duplicate-safe.
4. `/alerts`: điều kiện GTE/LTE, bật tắt, kiểm tra thủ công, notification đã đọc/chưa đọc.
5. `/tasks`: due date, bộ đếm quá hạn, hoàn thành và xóa.
6. `/learn`: 3 đến 6 bài học, tiến độ theo user, nội dung versioned.
7. `/simulation`: shock 10/20/30%, so sánh trước/sau, tuyệt đối không ghi thay đổi vào DB.
8. `/strategies`: thư viện chiến lược mẫu, số liệu fixture, nhãn “minh họa” và không tuyên bố backtest thật.

### P2 · Admin và vận hành

Admin nên được thêm khi có ít nhất một nhu cầu quản trị thật:

- xem user và khóa tài khoản demo;
- quản lý instrument/giá mô phỏng;
- quản lý bài học và chiến lược mẫu;
- xem lỗi API, notification và audit log;
- import/export fixture.

Thiết kế đề xuất:

- thêm `users.role` với `user` và `admin`, mặc định `user`;
- policy hoặc middleware `admin` cho `/admin/*`;
- admin dashboard dùng các số tổng hợp, không hiển thị mật khẩu hoặc credential;
- ghi `admin_audit_logs` cho thao tác sửa/xóa dữ liệu dùng chung;
- admin không được nhìn transaction riêng tư của user nếu chưa có yêu cầu nghiệp vụ rõ ràng;
- bản demo chỉ cần một admin seed trong môi trường local, không đưa password vào repo.

Không nên cài một admin panel lớn ngay khi chưa có nghiệp vụ. Nếu sau này cần CRUD nhanh, có thể đánh giá Filament sau khi chốt schema; hiện tại các màn hình Laravel + React/Inertia đang đủ và ít phụ thuộc hơn.

## AI Copilot

### Giai đoạn A · Nên làm ngay

Giữ Copilot deterministic như hiện tại, với ba intent allowlist:

- tóm tắt danh mục;
- tỷ trọng tiền mặt/chứng khoán/tài sản khác;
- tiến độ mục tiêu.

Mỗi câu trả lời lấy dữ liệu từ `PortfolioSummary`, ghi rõ “phân tích theo quy tắc” và không đưa khuyến nghị mua bán.

### Giai đoạn B · Nối LLM qua backend

Khi có API key và ngân sách, browser gửi câu hỏi đến Laravel. Laravel:

1. xác thực user và giới hạn tốc độ;
2. tạo context tối thiểu từ summary hiện tại;
3. loại bỏ email, password, request key và dữ liệu không cần thiết;
4. gọi provider qua HTTP client với timeout, retry cho GET hoặc request idempotent phù hợp;
5. kiểm tra output và thêm disclaimer;
6. không lưu prompt chứa dữ liệu nhạy cảm nếu chưa có chính sách;
7. trả câu trả lời cùng `source=llm`, `model`, `created_at` để audit.

Không gửi credential Supabase hoặc toàn bộ bảng cho model. Không cho model tự thực hiện transaction. Mọi lệnh mua/bán vẫn phải đi qua FormRequest và `RecordTransaction`.

### Có nên train model riêng không?

Chưa nên train trong phạm vi hiện tại. Fine-tuning cần dữ liệu hội thoại được làm sạch, tiêu chí đánh giá, chi phí và thời gian vận hành. Với MOFI, RAG trên tài liệu học tập và hàm công cụ đọc-only sẽ hữu ích hơn:

- tài liệu học tập làm knowledge base;
- tool đọc `PortfolioSummary`, goals và market demo;
- model chỉ diễn giải dữ liệu đã tính bởi server;
- câu hỏi ngoài phạm vi trả lời rõ là chưa hỗ trợ.

Có thể chạy Ollama local để thử nghiệm riêng, nhưng không dùng làm đường chính cho bản demo vì cần tải model lớn và hiệu năng phụ thuộc máy.

## Giá chứng khoán

Giai đoạn hiện tại dùng fixture mô phỏng cho cổ phiếu Việt Nam. Khi cần gần thực tế hơn:

1. tạo interface `MarketDataProvider`;
2. giữ `DemoMarketDataProvider` làm mặc định;
3. thêm provider thật sau khi xác nhận bản quyền, rate limit, SLA và đơn vị giá;
4. lưu raw response, thời điểm lấy, source và trạng thái lỗi;
5. không để provider thật thay đổi số liệu fixture demo nếu chưa có cờ cấu hình.

Binance public API tiếp tục là ví dụ giá thật cho crypto, tách khỏi danh mục cổ phiếu VND.

## UI/UX nâng cấp

- Giữ nền sáng, navy sidebar, xanh dương chính và mint cho trạng thái tăng.
- Dùng một hệ radius, spacing và typography; giữ Be Vietnam Pro.
- Thay các biểu đồ trang trí bằng chart có trục, tooltip, legend và giá trị đọc được.
- Hiển thị `Mô phỏng ngày 15/09/2026` ở mọi khối có dữ liệu fixture.
- Nút chưa hỗ trợ phải dẫn đến trang giới thiệu hoặc disabled kèm lý do.
- Không hiển thị testimonial, số người dùng hoặc hiệu suất như số liệu đã xác minh.
- Desktop là breakpoint nghiệm thu chính; kiểm tra mobile để không tràn ngang nhưng không mở rộng thêm tính năng mobile native.

## Thứ tự triển khai đề xuất

1. Chốt UI desktop và fixture dashboard.
2. Hoàn thiện giao dịch + lịch sử + preview.
3. Hoàn thiện assets/goals/watchlist/alerts/tasks/learn.
4. Thêm role admin và admin CRUD tối thiểu nếu bắt đầu có nhu cầu quản trị.
5. Thêm Copilot tool contract và provider interface.
6. Tích hợp LLM hoặc provider giá thật sau khi có key, ngân sách và chính sách dữ liệu.
7. CI/CD, logging, backup, deploy public và kiểm thử tải.

## Tiêu chí hoàn thành đợt tiếp theo

- Toàn bộ menu có đích đến và trạng thái rõ ràng.
- Giao dịch hợp lệ lưu/reload đúng; giao dịch sai không ghi một phần.
- User A/B không đọc hoặc sửa dữ liệu của nhau.
- Chart dùng cùng nguồn số liệu với metric và donut.
- Admin route bị từ chối với user thường.
- LLM hoặc provider ngoài bị timeout vẫn không làm hỏng dashboard.
- Không có credential trong Git, frontend bundle, screenshot hoặc tài liệu public.

### Tiến độ admin (18/09/2026)
Đã có role user/admin, middleware, trang giám sát chỉ đọc với phân trang và AdminSeeder riêng. Chưa triển khai CRUD/khóa tài khoản/audit log; các mục này vẫn thuộc P2. Mật khẩu admin được tạo ngẫu nhiên ở local, không đưa vào tài liệu public.
