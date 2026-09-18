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

## Rà soát chức năng lõi và lỗi cần xử lý

- [ ] Kiểm thử trình duyệt có đăng nhập cho toàn bộ menu, đặc biệt giao dịch Mua/Bán.
- [ ] Hoàn thiện trạng thái lỗi khi Inertia nhận response thiếu hoặc lỗi mạng; không để màn hình trắng.
- [ ] Tách props theo từng trang bằng partial reload/lazy props để chuyển trang không tải lại toàn bộ workspace.
- [ ] Dùng Supabase connection pooler và đo p50/p95 thời gian phản hồi.
- [ ] Thêm xác nhận giao dịch, xem trước phí/thuế và số dư sau giao dịch.
- [ ] Bổ sung validation khi không có mã tradable, giá thiếu hoặc dữ liệu market lỗi.
- [ ] Portfolio cần có lọc theo mã/thời gian, lịch sử giá và hiệu suất so sánh.
- [ ] Market cần trang chi tiết mã, tìm kiếm, watchlist và trạng thái nguồn dữ liệu.
- [ ] Strategy/Simulation cần lưu kịch bản, CAGR, drawdown và benchmark rõ ràng.
- [ ] Copilot cần lịch sử hội thoại, phạm vi dữ liệu và câu trả lời lỗi/timeout.
- [ ] Thêm E2E browser test cho auth, menu, giao dịch và thao tác admin trước demo.

# Kế hoạch nâng cấp MOFI theo ảnh mẫu

## 1. Đánh giá hiện trạng

MOFI hiện đã có nền tảng backend và các luồng tài chính mô phỏng: đăng ký/đăng nhập, dashboard, portfolio, giao dịch, mục tiêu, tài sản thủ công, watchlist, cảnh báo, học đầu tư, Copilot rule-based, admin và PostgreSQL/Supabase. So với ảnh mẫu, sản phẩm hiện đạt mức prototype chức năng.

So với ảnh dashboard mẫu, phần còn thiếu là mật độ module, card KPI, mục tiêu dạng tiến độ, market widget, AI panel, watchlist có sparkline, cảnh báo, task list và bốn khu vực Strategy Studio/Investment Lab/Học đầu tư/Cộng đồng được trình bày như sản phẩm hoàn thiện. So với ảnh landing mẫu, cần nâng hero, mockup laptop/điện thoại, CTA, thanh số liệu, nhóm sáu sản phẩm, AI band, mục tiêu cuộc sống, testimonial và footer.

MOFI chưa phải website chứng khoán thực tế. Đã có mô hình danh mục, giá vốn, lãi/lỗ, giao dịch mua/bán mô phỏng, biểu đồ và watchlist; chưa có sổ lệnh, bid/ask, khớp lệnh, tài khoản công ty chứng khoán, KYC, 2FA, tiền thật, phí sàn thật hay dữ liệu cổ phiếu Việt Nam realtime.

## 2. Mục tiêu nghiệm thu

- Desktop 1366px là kích thước nghiệm thu chính; mobile chỉ cần không tràn ngang.
- Dashboard và landing có cảm giác giống ảnh mẫu nhưng vẫn ghi rõ dữ liệu mô phỏng.
- Mọi menu đều có trang, trạng thái loading, trạng thái rỗng và trạng thái lỗi.
- Luồng Mua/Bán không còn màn hình trắng; giao dịch hợp lệ cập nhật lịch sử và tổng tài sản.
- Điều hướng workspace sau lần tải đầu mục tiêu dưới 1 giây khi cache còn hiệu lực.
- Không đưa credential, dữ liệu cá nhân hoặc số liệu demo chưa xác minh vào tài liệu public.

## 3. Lộ trình triển khai theo thứ tự

### P0 — Ổn định lõi trước khi làm đẹp

1. Kiểm thử browser có đăng nhập cho toàn bộ menu.
2. Kiểm tra Giao dịch: Nạp, Rút, Mua, Bán, Cổ tức, giao dịch lặp và lỗi kết nối.
3. Sửa mọi response làm React trắng màn hình; giữ Error Boundary và thông báo lỗi rõ ràng.
4. Thêm preview giao dịch: mã, số lượng, giá, phí, thuế, tiền thay đổi và số dư sau giao dịch.
5. Kiểm tra quyền User A/B, admin, portfolio ownership và dữ liệu Supabase RLS.
6. Thêm E2E test cho đăng nhập, chuyển menu, chọn Mua, submit giao dịch và admin.

### P1 — Design system và dashboard theo ảnh đầu tiên

1. Chuẩn hóa màu: nền xanh rất nhạt, navy sidebar, xanh dương chính, xanh mint tăng trưởng, đỏ/cam giảm giá.
2. Chuẩn hóa typography, radius, shadow, border, spacing, icon và trạng thái focus.
3. Tạo component dùng lại: `MetricCard`, `ChartCard`, `TableCard`, `ProgressCard`, `Badge`, `Toast`, `Modal`, `Skeleton`, `EmptyState`.
4. Dashboard gồm sidebar, topbar search/notification/avatar, hero greeting, bốn KPI card, allocation, goals, market, Copilot, portfolio chart, watchlist, alerts, tasks và bốn product card.
5. Thêm tabs 1D/1W/1M/3M/1Y/All cho biểu đồ danh mục.
6. Thêm tooltip, legend, trục, đơn vị tiền và ngày dữ liệu cho mọi biểu đồ.

### P1 — Landing theo ảnh thứ hai

1. Navbar rõ CTA đăng nhập/đăng ký.
2. Hero headline nhiều tầng màu, CTA kép và mockup dashboard laptop/điện thoại.
3. Thanh thống kê có nhãn dữ liệu demo.
4. Sáu sản phẩm: Tra cứu, Quản lý tài sản, Chiến lược, Sàn tập ảo, Học đầu tư, AI Copilot.
5. AI Copilot band nền navy.
6. Mục tiêu cuộc sống: mua nhà, học cho con, nghỉ hưu, mua xe, quỹ dự phòng, tự do tài chính.
7. Persona/testimonial dùng nhãn minh họa, không trình bày như đánh giá thật.
8. CTA cuối trang và footer đầy đủ.

### P1 — Chuyển động và tương tác

- Stagger reveal khi dashboard tải.
- Count-up cho KPI.
- Vẽ chart dần khi vào viewport.
- Progress bar chạy tới giá trị thật.
- Hover card nâng nhẹ và đổi border.
- Sidebar active có chuyển động trượt nhẹ.
- Modal fade/scale, toast slide-in, button loading/success/error.
- AI Copilot có typing effect cho câu trả lời mô phỏng.
- Tabs, tooltip và dropdown chuyển cảnh mượt.
- Landing có scroll reveal và parallax rất nhẹ.
- Tôn trọng `prefers-reduced-motion`; không dùng animation gây khó đọc ở bảng giao dịch.

### P2 — Chức năng giống nền tảng đầu tư hơn

1. Portfolio: lọc mã/ngành, lịch sử giao dịch, giá vốn, giá hiện tại, lãi/lỗ từng mã, tỷ trọng và benchmark.
2. Market: trang chi tiết mã, tìm kiếm, nhóm Việt Nam/thế giới/hàng hóa/crypto, watchlist, chart nhiều khoảng thời gian và trạng thái nguồn.
3. Strategy Studio: tạo chiến lược, tỷ trọng, lưu chiến lược, CAGR, drawdown, Sharpe mô phỏng.
4. Investment Lab: kịch bản VN-Index giảm, lãi suất tăng, crypto giảm, tăng tiền mặt; so sánh trước/sau.
5. Goals: mẫu mục tiêu, số tiền cần tiết kiệm mỗi tháng, tiến độ và ngày dự kiến hoàn thành.
6. Learning: bài học, quiz ngắn, tiến độ và badge.
7. Community: strategy card, theo dõi và xếp hạng mô phỏng; không sao chép giao dịch thật.
8. Copilot: lịch sử hội thoại, câu hỏi gợi ý, phạm vi dữ liệu, timeout/error state và disclaimer.

### P2 — Hiệu năng và vận hành

1. Chuyển Supabase sang connection pooler.
2. Tách props theo từng trang bằng Inertia partial reload/lazy props.
3. Cache market data dùng chung; cache summary có invalidation sau giao dịch.
4. Lazy-load chart và code-split bundle React lớn.
5. Thêm index cho giao dịch, market prices, watchlist và alerts.
6. Dùng pagination cho bảng dài.
7. Thêm skeleton để người dùng không thấy màn hình trắng.
8. Đo p50/p95 cho landing, login, dashboard, market và giao dịch.
9. Production build, nén ảnh, logging lỗi và health check.

### P3 — Admin và dữ liệu thật có kiểm soát

- Admin filter/search user, trạng thái, lần đăng nhập cuối.
- Audit log filter/export CSV.
- Thống kê lỗi API, cache và thời gian phản hồi.
- Interface `MarketDataProvider`, giữ provider fixture mặc định.
- Thêm provider cổ phiếu thật chỉ sau khi có nguồn hợp pháp, rate limit, SLA và cờ cấu hình.
- Crypto Binance tiếp tục tách khỏi danh mục cổ phiếu VND.
- AI dùng provider interface/RAG/tool đọc-only; chưa train model riêng trong giai đoạn demo.

## 4. Tiêu chí duyệt trước khi code tiếp

- Chốt dashboard desktop theo ảnh đầu tiên.
- Chốt landing theo ảnh thứ hai.
- Chọn thứ tự P0 → P1 → P2.
- Xác nhận dữ liệu cổ phiếu Việt Nam tiếp tục là fixture mô phỏng.
- Xác nhận Copilot vẫn rule-based trong bản demo.
- Xác nhận giao dịch vẫn là tiền ảo, không kết nối tiền thật.

Sau khi duyệt, triển khai theo P0 trước để ổn định luồng lõi, sau đó làm design system và dashboard trước khi mở rộng Strategy Studio, Investment Lab và Community.

### Cập nhật P0 (18/09/2026)

- [x] Đã kiểm tra browser: đăng nhập demo và mở trang Giao dịch.
- [x] Đã chọn Mua thành công trong browser với dữ liệu demo hiện tại; chưa tái hiện được nguyên nhân lỗi trắng màn hình ban đầu.
- [x] Đã thêm Error Boundary và trạng thái lỗi rõ ràng cho workspace.
- [x] Đã thêm xem trước giá trị, phí, thuế và tổng thanh toán trước khi ghi giao dịch.
- [x] Đã build frontend và chạy `70 passed, 1 skipped`.
- [ ] Còn lại: kiểm thử submit Mua/Bán trên tài khoản kiểm thử riêng; không cần thay đổi fixture A/B.

### Cập nhật hiệu năng P2

- [x] Tách truy vấn workspace theo trang; trang giao dịch không tải goals/assets/alerts/learning không cần thiết.
- [x] Trang không cần định giá đầy đủ dùng summary rút gọn.
- [x] Cập nhật test để phân biệt trang cần summary đầy đủ và trang chỉ cần dữ liệu riêng.
- [ ] Độ trễ phiên browser vẫn khoảng 10 giây; cần chuyển Supabase sang connection pooler và đo lại sau khi đổi endpoint.

### Kiểm chứng sau tối ưu theo trang

- Đã sửa hồi quy: Copilot vẫn nhận mục tiêu; cả Alerts và Notifications nhận đủ cảnh báo, mã tài sản và thông báo.
- Có kiểm thử hồi quy cho các props này và đảm bảo Transactions không tải các module không cần thiết.
- Đã thêm thanh trạng thái khi Inertia chuyển trang; đây là phản hồi giao diện, không phải cải thiện thời gian backend.
- Kiểm tra: 71 test pass, 1 skipped; TypeScript và build thành công. Bundle lớn vẫn cần tách.
- Pooler chưa được áp dụng. Cần lấy endpoint Session pooler thật từ Supabase, kiểm tra TLS và đo so sánh trước khi thay .env.
- Các số đo khoảng 10 giây từ công cụ browser trước đây chưa tách được overhead công cụ khỏi TTFB. Chưa đủ bằng chứng kết luận nguyên nhân là database; chưa nghiệm thu mục tiêu tốc độ.
