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
3. Tách rõ màn hình cash flow và order flow: Transactions preview nạp/rút; Market preview cash/quantity/reservation trước paper order.
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

Không gửi credential Supabase hoặc toàn bộ bảng cho model. Không cho model tự thực hiện transaction. Mọi lệnh mua/bán phải đi qua FormRequest và `PaperTradingService`; `RecordTransaction` chỉ xử lý nạp/rút.

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
Đã có role user/admin, middleware, trang giám sát có phân trang, lọc tài khoản/mã tài sản, khóa/mở khóa và audit log; AdminSeeder riêng tạo tài khoản local. Mật khẩu admin được tạo ngẫu nhiên ở local, không đưa vào tài liệu public.

### Cập nhật provider, vận hành và giao diện (18/09/2026)

- [x] Tách `MarketDataProvider` khỏi controller; Binance crypto preview có timeout, cache, nhãn nguồn và hợp đồng provider để thay nguồn sau này.
- [x] API giao dịch trả receipt gồm mã biên nhận, loại, ngày, giá trị gộp, phí, thuế, dòng tiền và trạng thái replay; không trả `request_hash`/`request_key`.
- [x] Thêm endpoint xuất CSV giao dịch theo bộ lọc loại, mã và khoảng ngày; có UTF-8 BOM, owner-scoped và không chứa bí mật.
- [x] Thêm `/admin/health` kiểm tra database, cache, danh mục demo và giá mô phỏng; chỉ admin được gọi và không lộ cấu hình.
- [x] Tách `AlertEvaluationService` để giữ logic chuyển trạng thái false → true, bỏ qua giá thiếu và khóa rule trong transaction.
- [x] Portfolio có bộ lọc mã đang nắm giữ; landing có pointer tilt nhẹ trên mockup, receipt animation và hover/pressed feedback, tôn trọng reduced-motion.
- [x] Test suite sau đợt này: 84 pass, 1 skipped, 1.015 assertions; TypeScript, Pint và Vite build đạt.

## Rà soát chức năng lõi và các giới hạn còn lại

- [x] Kiểm thử browser các luồng giao dịch Mua/Bán, Community và Copilot; feature suite kiểm tra toàn bộ menu private.
- [x] Có ErrorBoundary, error banner, loading progress và trạng thái lỗi cho market/transaction; không để response thiếu làm màn hình trắng.
- [x] Tách props theo từng trang, cache market/summary và lazy-load chart; không tải module nặng khi không cần.
- [ ] Dùng Supabase connection pooler và đo p50/p95 thời gian phản hồi.
- [x] Giao dịch có xem trước phí/thuế, số dư dự kiến, idempotency retry và lịch sử immutable.
- [x] Validation khi không có mã tradable, giá thiếu hoặc dữ liệu market lỗi có thông báo rõ.
- [ ] Portfolio lọc theo mã/thời gian, lịch sử giá và hiệu suất so sánh — để sau bản demo lõi.
- [x] Market có tìm kiếm, watchlist, sparkline 30 ngày và nhãn nguồn dữ liệu mô phỏng; trang detail realtime để sau.
- [x] Strategy/Simulation lưu chiến lược và shock scenario; CAGR, drawdown và benchmark thật để sau khi có provider lịch sử.
- [x] Copilot có lịch sử user-scoped, phạm vi deterministic và câu trả lời unsupported; không có timeout provider vì chưa gọi LLM.
- [ ] E2E browser automation đầy đủ — hiện dùng browser QA thủ công kết hợp feature tests; sẽ thêm khi chốt runner hosting.

Các mục chưa tick bên trên là hạng mục mở rộng sau bản demo: pooler chỉ thay đổi khi đo được lợi ích trên môi trường đích, portfolio nâng cao cần thêm yêu cầu UX, và E2E cần runner/hosting ổn định. Chúng không chặn nghiệm thu bản demo local/Supabase đã được duyệt.

# Kế hoạch nâng cấp MOFI theo ảnh mẫu

## 1. Đánh giá hiện trạng

MOFI hiện đã có nền tảng backend và các luồng tài chính mô phỏng: đăng ký/đăng nhập, dashboard, portfolio, giao dịch, mục tiêu, tài sản thủ công, watchlist, cảnh báo, học đầu tư, Copilot rule-based, admin và PostgreSQL/Supabase. So với ảnh mẫu, sản phẩm hiện đạt mức prototype chức năng.

So với ảnh dashboard mẫu, phần còn thiếu là mật độ module, card KPI, mục tiêu dạng tiến độ, market widget, AI panel, watchlist có sparkline, cảnh báo, task list và bốn khu vực Strategy Studio/Investment Lab/Học đầu tư/Cộng đồng được trình bày như sản phẩm hoàn thiện. So với ảnh landing mẫu, cần nâng hero, mockup laptop/điện thoại, CTA, thanh số liệu, nhóm sáu sản phẩm, AI band, mục tiêu cuộc sống, testimonial và footer.

MOFI chưa phải website chứng khoán thực tế. Bản demo hiện đã có quote board mô phỏng, bid/ask depth, đồng hồ phiên, Market/Limit paper order, reservation, execution, partial fill, cancel, transaction reconciliation, biểu đồ OHLC ngày và watchlist. Chưa có tài khoản công ty chứng khoán, KYC, 2FA, tiền thật, phí sàn thật, broker adapter hay dữ liệu cổ phiếu Việt Nam realtime.

## 2. Mục tiêu nghiệm thu

- Desktop 1366px là kích thước nghiệm thu chính; mobile chỉ cần không tràn ngang.
- Dashboard và landing có cảm giác giống ảnh mẫu nhưng vẫn ghi rõ dữ liệu mô phỏng.
- Mọi menu đều có trang, trạng thái loading, trạng thái rỗng và trạng thái lỗi.
- Luồng paper Mua/Bán ở Market cập nhật order, execution, lịch sử và tổng tài sản; Transactions chỉ còn Nạp/Rút.
- Điều hướng workspace sau lần tải đầu mục tiêu dưới 1 giây khi cache còn hiệu lực.
- Không đưa credential, dữ liệu cá nhân hoặc số liệu demo chưa xác minh vào tài liệu public.

## 3. Lộ trình triển khai theo thứ tự

### P0 — Ổn định lõi trước khi làm đẹp

1. Kiểm thử browser có đăng nhập cho toàn bộ menu.
2. Kiểm tra Transactions: Nạp/Rút; kiểm tra Market: Mua/Bán, khớp/hủy, giao dịch lặp và lỗi kết nối.
3. Sửa mọi response làm React trắng màn hình; giữ Error Boundary và thông báo lỗi rõ ràng.
4. Thêm preview giao dịch: mã, số lượng, giá, phí, thuế, tiền thay đổi và số dư sau giao dịch.
5. Kiểm tra quyền User A/B, admin, portfolio ownership và dữ liệu Supabase RLS.
6. Thêm E2E test cho đăng nhập, chuyển menu, đặt paper order trong Market và admin.

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

1. Đo direct và Session pooler; chọn endpoint theo kết quả thực tế (máy hiện tại giữ direct).
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
- [x] Đã chọn Mua thành công trong Market browser flow với dữ liệu demo; lỗi trắng màn hình được thay bằng ErrorBoundary và trạng thái lỗi rõ.
- [x] Đã thêm Error Boundary và trạng thái lỗi rõ ràng cho workspace.
- [x] Đã thêm xem trước giá trị, phí, thuế và tổng thanh toán trước khi ghi giao dịch.
- [x] Đã build frontend và chạy `70 passed, 1 skipped`.
- [x] Browser QA đã kiểm tra tách luồng trên tài khoản riêng: nạp 2.000.000 VND ảo ở Transactions, mua/bán paper order ở Market; reload giữ đúng số dư, order và lịch sử execution.

### Cập nhật hiệu năng P2

- [x] Tách truy vấn workspace theo trang; trang giao dịch không tải goals/assets/alerts/learning không cần thiết.
- [x] Trang không cần định giá đầy đủ dùng summary rút gọn.
- [x] Cập nhật test để phân biệt trang cần summary đầy đủ và trang chỉ cần dữ liệu riêng.
- [x] Đo lại HTTP local sau tối ưu: landing khoảng 377 ms và redirect dashboard khoảng 353 ms trong lần kiểm tra hiện tại; đây là mẫu local, chưa là SLA production.

### Kiểm chứng sau tối ưu theo trang

- Đã sửa hồi quy: Copilot vẫn nhận mục tiêu; cả Alerts và Notifications nhận đủ cảnh báo, mã tài sản và thông báo.
- Có kiểm thử hồi quy cho các props này và đảm bảo Transactions không tải các module không cần thiết.
- Đã thêm thanh trạng thái khi Inertia chuyển trang; đây là phản hồi giao diện, không phải cải thiện thời gian backend.
- Kiểm tra: 71 test pass, 1 skipped; TypeScript và build thành công. Bundle lớn vẫn cần tách.
- Pooler chưa được áp dụng. Cần lấy endpoint Session pooler thật từ Supabase, kiểm tra TLS và đo so sánh trước khi thay .env.
- Các số đo khoảng 10 giây từ công cụ browser trước đây chưa tách được overhead công cụ khỏi TTFB. Chưa đủ bằng chứng kết luận nguyên nhân là database; chưa nghiệm thu mục tiêu tốc độ.

### Đo PostgreSQL và áp dụng cấu hình (18/09/2026)

- Direct: kết nối mới khoảng 2,96–3,06 giây; `select 1` khoảng 0,86–0,89 giây. Session pooler: kết nối 3,00–3,16 giây; truy vấn 1,00–1,02 giây. Giữ direct và TLS, không đổi endpoint theo phỏng đoán.
- Trên cùng kết nối direct, truy vấn có tham số giảm từ khoảng 875 ms xuống 292 ms khi bật PDO emulated prepares. Vẫn truyền bindings qua PDO, không nối dữ liệu người dùng vào SQL.
- Tính summary không cache: 11.309 ms → 5.577 ms; kết quả hai chế độ giống nhau.
- Đã bật `DB_EMULATE_PREPARES=true` trong `.env` cục bộ và xóa config cache. Có thể hoàn tác bằng `false` rồi chạy `php artisan config:clear`. Mặc định repository vẫn `false`.

| Luồng backend, cache rỗng | Native prepares | Emulated prepares |
| --- | ---: | ---: |
| Giao dịch | 10.957 ms | 5.223 ms |
| Tổng quan | 19.447 ms | 8.356 ms |
| Thị trường | 7.651 ms | 4.019 ms |

Phương pháp: gọi controller thật với user demo, cache array rỗng và kết nối mới cho mỗi trang. Mỗi ô là một mẫu; gồm đọc user và tải props, chưa gồm HTTP middleware, render React, tải tài nguyên hay network của trình duyệt. Đây không phải p50/p95 hoặc thời gian tải trang hoàn chỉnh. Số liệu cho thấy cải thiện, chưa đạt mục tiêu phản hồi nhanh.

Kiểm tra HTTP riêng sau thay đổi: landing `/` trả 200, TTFB 5,67 giây (một mẫu localhost). Trang công khai vẫn chậm, nên tối ưu PostgreSQL chưa giải quyết toàn bộ độ trễ; cần tách tiếp thời gian bootstrap/server và middleware.

- Kiểm thử PostgreSQL riêng tại localhost:55439 chạy thành công ở cả hai chế độ: Unicode, dấu nháy/backslash trong binding, decimal chính xác, boolean, bán đồng thời và chống gửi trùng (16 assertions mỗi chế độ). Chỉ database thử nghiệm bị reset; không reset Supabase.
- Toàn bộ suite: 71 pass, 1 skip (test PostgreSQL opt-in đã chạy riêng), 849 assertions. Pint thành công.
- Tiếp theo: đo HTTP có đăng nhập và submit trên tài khoản thử riêng; giảm truy vấn trùng; kiểm tra invalidation cache sau commit/admin; tách bundle chart. Nếu triển khai online, đặt backend gần vùng database và đo lại.
- P0/P1 đã nghiệm thu bằng feature suite và browser QA; các hạng mục mở rộng dài hạn được giữ riêng bên dưới, không thuộc bản demo hai ngày.

### Sửa lỗi cache và giao dịch trên browser (18/09/2026)

- [x] Cache market chuyển từ object Eloquent sang mảng thuần, tương thích cấu hình không cho unserialize class; thêm test round-trip với file cache.
- [x] Cache market được version hóa và invalidation sau commit khi admin bật/tắt tài sản.
- [x] Invalidation summary chạy sau commit để giao dịch rollback không xóa cache hợp lệ.
- [x] Đăng xuất từ Inertia dùng full navigation về trang chủ, tránh hiển thị trang công khai trong hộp lỗi.
- [x] Tách bootstrap Inertia khỏi component Workspace để tránh gọi `createRoot` hai lần khi HMR/navigation.
- [x] Browser QA bằng tài khoản riêng: nạp 2.000.000 VND ảo, mua 10 MOFI, bán 4 MOFI; số dư, phí, thuế và lịch sử hiển thị đúng sau reload.
- [x] Focused tests: 53 pass, 639 assertions; full suite trước đó 74 pass, 1 skipped sau thay đổi frontend/cache. TypeScript và production build thành công.
- [x] Đã giữ đúng phạm vi: không làm giao dịch tiền thật hoặc kết nối chứng khoán thật; mọi giao dịch browser vẫn là mô phỏng.

### Ổn định biểu đồ frontend (18/09/2026)

- [x] Thêm kích thước tối thiểu cho biểu đồ danh mục, phân bổ, sparkline và crypto chart để Recharts không khởi tạo với kích thước 0 khi Inertia chuyển trang.
- [x] TypeScript, production build và full PHPUnit đều đạt sau thay đổi; bundle workspace hiện khoảng 227 KB (gzip khoảng 67 KB), còn chunk runtime Recharts khoảng 578 KB (gzip khoảng 172 KB).
- [x] Chart workspace đã lazy-load bằng dynamic import; manifest hiện không còn chunk trên 500 KB và initial workspace khoảng 200 KB raw/59 KB gzip ở build mới.

### Tải biểu đồ theo nhu cầu

- Đã tách các chart workspace sang `Charts.tsx`, dùng React lazy/Suspense; trang không render chart không yêu cầu chunk này. Có trạng thái chờ riêng cho từng chart.
- Kiểm tra dependency graph trong manifest production: JS ban đầu của workspace giảm từ khoảng 805 KB xuống 441 KB, gzip từ khoảng 239 KB xuống 133 KB. Đây là dung lượng asset, không phải số đo TTFB hay tốc độ truy vấn DB.
- Trang market đã được kiểm tra trên browser: trạng thái chờ được thay bằng 4 sparkline, không có warning/error trong lần kiểm tra. TypeScript và build thành công; build không còn chunk trên 500 KB.
- Chart vẫn dùng chung một chunk lazy; chưa tách riêng thư viện theo từng loại biểu đồ. Landing vẫn tải chart của bản minh họa theo cách hiện có.

### Hoàn thiện luồng lịch sử và bàn giao (18/09/2026)

- [x] Lịch sử giao dịch có bộ lọc loại, mã tài sản, từ ngày và đến ngày; query được validate ở server và giữ phân trang.
- [x] Mục tiêu hiển thị hạn hoàn thành và số tiền cần thêm mỗi tháng khi có ngày dự kiến.
- [x] Tạo `DELIVERY.md` với phạm vi demo, kịch bản trình bày, lệnh chạy và kết quả kiểm thử.
- [x] Full suite sau các thay đổi: 75 tests, 74 passed, 1 skipped; focused workspace: 12 passed.
- [x] Admin có tìm kiếm/lọc tài khoản theo tên/email/trạng thái và tìm mã tài sản theo mã/tên; phân trang tiếp tục giữ nguyên.
- [x] Goal card hiển thị hạn, trạng thái quá hạn/hoàn thành và số tiền cần bổ sung mỗi tháng nếu có ngày dự kiến.
- [x] Strategy Studio có migration/model/factory, validation tổng tỷ trọng 100% và lưu theo user.
- [x] Investment Lab có lưu kịch bản shock, hiển thị lịch sử kịch bản theo user và không ghi transaction.
- [x] Migration Strategy/Simulation đã chạy trên Supabase và browser đã lưu thành công một chiến lược demo.

### Community và Copilot (18/09/2026)

- [x] Community có bảng bài viết, form đăng bài, danh sách bài mới nhất và xóa bài theo đúng chủ sở hữu.
- [x] Copilot lưu câu hỏi/câu trả lời rule-based theo từng user; không nhận answer/source/user_id từ client.
- [x] Community/Copilot props được giới hạn theo trang và user; test kiểm tra không rò email/password và chống truy cập chéo user.
- [x] Migration `2026_09_18_150000_create_community_and_copilot_tables` đã chạy ở môi trường local và bật RLS/revoke cho PostgreSQL.
- [x] Full PHPUnit sau thay đổi: 81 passed, 1 skipped, 989 assertions; TypeScript và Vite production build đạt.
- [x] Đã xác nhận không tích hợp provider giá cổ phiếu thật hoặc LLM trả phí; đây là giới hạn cố ý của bản demo.
- [x] GitHub Actions workflow kiểm tra format PHP, PHPUnit, TypeScript và Vite build trên pull request/push.
- [x] Workspace có focus ring, skip link, reduced-motion support, hover/pressed states và staggered page reveal.

### Kiểm chứng tính đúng của kịch bản

- Máy chủ tính before/after/change từ PortfolioSummary mới, dùng BigDecimal và làm tròn VND; bỏ qua số tiền do client gửi. Thiếu giá thì trả validation error và không lưu.
- Lưu kịch bản không thay đổi giao dịch hoặc số dư; test fixture A giảm chứng khoán 20% cho tổng tài sản 38.315.000 → 34.565.000 VND, chênh lệch -3.750.000 VND.
- Migration bảo vệ hai bảng mới đã áp dụng lên Supabase: xác minh RLS bật, anon/authenticated không có quyền SELECT trực tiếp.
- Sửa form mẫu chiến lược để giá trị input đổi đồng bộ khi chọn Thận trọng/Tăng trưởng.
- Kiểm thử workspace: 14 pass; TypeScript và build thành công. Bản demo đã đủ điều kiện nghiệm thu; các mở rộng dài hạn được ghi rõ là ngoài phạm vi.

## Trạng thái màn hình giao dịch theo thời gian

Màn hình Market hiện là paper-trading terminal mô phỏng, chưa phải hệ thống đặt lệnh thật. Không cần API thật để trình bày trải nghiệm bảng giá, chart và lệnh chờ.

1. Đã có: chọn mã, giá hiện tại, bảng bid/ask mô phỏng, form Mua/Bán, Market/Limit, lệnh mở, partial fill, cancel, execution history và ngày/giờ mô phỏng.
2. Đã có: phiên replay deterministic từ tick server; client polling lấy board; refresh không làm đổi tick đã xử lý.
3. Đã có: bảng `orders` tách khỏi `transactions`; chỉ execution khớp mới tạo transaction.
4. Đã có: available cash = cash - reservation BUY; available quantity = holdings - reservation SELL; cancel giải phóng phần giữ.
5. Đã có: Market ăn Ask/Bid tối đa ba depth level; Limit kiểm tra điều kiện đối ứng; volume tạo partial fill.
6. Đã có: decimal/BigDecimal, idempotency, lock, rollback và replay tick guard.
7. Cần mở rộng sau demo: nhiều phiên giao dịch, phí/thuế theo biểu phí thật, slippage, queue priority, matching giữa nhiều user và broker integration.

API thị trường thật chỉ cần khi muốn chart theo giá thực tế. Giao dịch thật cần thêm API broker, tài khoản được cấp quyền và quy trình vận hành riêng. Ưu tiên demo: replay fixture + chart + lệnh limit trước, không nối broker.

### Sửa layout giao dịch

Đã sửa flex sizing của dashboard, min-width của grid/form và chuyển bộ lọc sang grid hai cột. Kiểm tra browser ở 1366px và 1920px: nội dung bắt đầu sau sidebar 220px, không tràn ngang tài liệu, các bộ lọc nằm trong panel. Vite build đạt.

## Định hướng dashboard theo hai ảnh tham chiếu

Nên theo đúng cấu trúc hai ảnh: landing để kể câu chuyện sản phẩm, dashboard để chứng minh dữ liệu và thao tác. Không nên biến mọi card thành chức năng độc lập thiếu logic; mỗi khu vực cần gắn với dữ liệu hoặc trạng thái rõ ràng.

### Dashboard sau đăng nhập

- Sidebar có thể thu gọn/mở rộng trên desktop bằng nút cạnh logo; khi thu gọn chỉ giữ icon, khi mở lại trả đầy đủ nhãn. Mobile vẫn dùng drawer.
- Header giữ tìm kiếm, thông báo, avatar và trạng thái tài khoản.
- Hero giữ lời chào, ngày mô phỏng và ảnh nền; KPI lấy từ cùng summary backend.
- Các vùng theo ảnh: allocation, goals, market index, Copilot, portfolio chart, watchlist, alerts, tasks và bốn module Strategy/Simulation/Learn/Community.
- Card chỉ hiển thị số liệu có nguồn; chức năng chưa có provider phải dùng nhãn “mô phỏng” hoặc “đang phát triển”.

### Những chức năng nên nâng cấp tiếp

1. Paper trading nâng cao: nhiều phiên, slippage, queue priority, phí/thuế theo thị trường và benchmark.
2. Portfolio: lọc theo mã, hiệu suất 1D/1W/1M/3M/1Y, benchmark fixture và drill-down cost basis.
3. Goals: số tiền cần mỗi tháng, trạng thái quá hạn, ưu tiên mục tiêu và biểu đồ tiến độ.
4. Market: tab Việt Nam/thế giới/hàng hóa/crypto, quote detail, volume và nguồn dữ liệu.
5. Admin: dashboard health, audit filter/export, thống kê lỗi provider và cache hit rate.
6. Copilot: giữ rule-based ở demo; về sau mới thêm provider AI đọc-only với timeout, chi phí và guardrail.

Paper trading không yêu cầu API thật. API thật chỉ cần cho quote thực tế; khớp lệnh thật còn cần broker API, credential, compliance và cơ chế đối soát riêng.


## Tiến độ triển khai paper trading (19/09/2026)

- [x] Tạo bảng `orders`, `order_reservations`, `executions` trên PostgreSQL/Supabase và bật RLS/revoke cho Data API.
- [x] Thêm `PaperTradingService`: giữ tiền/cổ phiếu, market/limit matching theo quote mô phỏng, execution một lần, transaction ledger và cancel.
- [x] Thêm API list/create/cancel order, request key/hash và owner scope.
- [x] Thêm form đặt lệnh mô phỏng vào `/market`, danh sách lệnh gần đây và nút hủy.
- [x] Test limit OPEN/reservation, market fill/replay, thiếu vị thế, cross-owner và cancel idempotent.
- [x] Full suite sau phần lõi: 92 pass, 1 skipped; TypeScript, Pint và Vite build đạt.

Phần paper trading lõi đã hoàn tất: volume histogram, khoảng thời gian chart, advance tick endpoint, tick monotonic và partial fill/hủy phần còn lại đều có. Các hạng mục sau demo còn lại là broker thật, provider giá thật, benchmark và AI provider có kiểm soát.
