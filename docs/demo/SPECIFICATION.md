# Đặc tả website MOFI Demo

## 1 Mục tiêu và giới hạn

Sản phẩm phục vụ buổi đánh giá năng lực trong hai ngày phát triển. Người xem có thể đi từ landing đến đăng nhập, xem dashboard, nạp/rút tiền ảo và đặt paper order trong bảng giá mô phỏng. Hai ảnh là tham chiếu bố cục, màu và trải nghiệm, không là nguồn dữ liệu tài chính đúng.

Cần phân biệt ba mức: **Hoạt động** là thao tác có xử lý và lưu DB; **Mô phỏng** là thao tác trên dữ liệu/kịch bản giả có nhãn; **Giới thiệu** là trang có nội dung và giải thích phần chưa hỗ trợ. Không hiển thị thông báo mua Pro/thanh toán/lệnh chứng khoán thành công nếu chưa có nghiệp vụ đó.

## 2 Kiến trúc và công nghệ

PHP là ngôn ngữ backend; Laravel là framework PHP phụ trách route, validation, đăng nhập, policy và truy vấn database. React là thư viện giao diện; TypeScript bổ sung kiểm tra kiểu; Inertia nối trang React với Laravel trong cùng repository. Tailwind tổ chức CSS, Vite build frontend, Recharts vẽ dữ liệu server trả về. PostgreSQL là database; Supabase cung cấp nơi chạy PostgreSQL. Composer cài package PHP, npm cài package frontend, GitHub giữ source và lịch sử thay đổi.

Luồng: browser -> Laravel session/CSRF/policy -> service tính toán/matching -> PostgreSQL -> props/response -> React. Browser không kết nối trực tiếp bảng Supabase và không chứa password DB. Bảng giá và chart không tự cung cấp giá; quote/replay được server seed và đồng bộ. Không cần backend Node riêng, Supabase Auth, Redis hoặc API AI trả phí cho demo này.

## 3 Màn hình và mức hỗ trợ

| Mã | Route và khu vực | Mức | Hành vi nghiệm thu |
| --- | --- | --- | --- |
| D01 | `/` landing | Hoạt động | Header, hero, thống kê mẫu, 6 sản phẩm, AI preview, thị trường, mục tiêu, testimonials mẫu, CTA, footer như ảnh |
| D02 | `/products`, `/about`, `/pricing`, `/policies` | Giới thiệu | Điều hướng không 404; Pro ghi chưa thanh toán; chính sách giải thích dữ liệu demo |
| D03 | `/login`, `/register`, `/settings` | Hoạt động | Login/logout/register, hash password, sửa tên hồ sơ; user mới có portfolio rỗng |
| D04 | `/dashboard` | Hoạt động | Tổng tài sản/cash/securities/P&L, donut, mục tiêu, market, summary, chart, watchlist, alert, tasks, 4 tile dưới cùng |
| D05 | `/assets` | Hoạt động | Xem cash, danh mục và thêm/sửa/xóa tài sản thủ công bằng giá trị VND |
| D06 | `/portfolio`, `/transactions` | Hoạt động | Portfolio hiển thị holdings/P&L; Transactions chỉ deposit/withdraw; lịch sử vẫn hiển thị BUY/SELL đã khớp để đối soát |
| D07 | `/goals` | Hoạt động | Tạo/sửa/xóa mục tiêu; cập nhật tiền đã dành, target/deadline, progress |
| D08 | `/market`, `/watchlist` | Hoạt động với giá mẫu | Bảng giá MOFI có last/reference/ceiling/floor, bid/ask, volume, simulated date/time, Market/Limit order và lịch sử lệnh; watchlist dùng dữ liệu mẫu đúng đơn vị |
| D09 | `/alerts`, `/notifications` | Mô phỏng có lưu | Tạo ngưỡng giá, bật/tắt, bấm kiểm tra giá mẫu; một notification khi chuyển false->true; đọc/chưa đọc |
| D10 | `/tasks` và khối dashboard | Hoạt động | Thêm/xóa và đánh dấu hoàn thành; bộ đếm khớp danh sách |
| D11 | `/copilot` | Mô phỏng | Câu hỏi gợi ý trả summary từ dữ liệu hiện tại; câu tự do ngoài mẫu báo giới hạn; không giả là LLM thật |
| D12 | `/strategies` | Mô phỏng | Chọn chiến lược mẫu, mở chi tiết và chart kết quả cố định; không chạy backtest thật |
| D13 | `/simulation` | Mô phỏng tính toán | Chọn kịch bản giá giảm 10/20%; tính q*price*(1-rate) trên vị thế hiện tại, không ghi vào tài sản |
| D14 | `/learn` | Nội dung mẫu + lưu tiến độ | 3 bài có nội dung ngắn, đọc/đánh dấu xong; tiến độ reload giữ nguyên |
| D15 | `/community` | Hoạt động mô phỏng | Thành viên đăng bài, xem bài mới nhất và chỉ xóa được bài của mình; nội dung có nhãn không phải tư vấn đầu tư |

Các page dùng sidebar/header chung, breadcrumb/title rõ, mobile menu hoạt động. Button không hỗ trợ thì disabled kèm lý do hoặc điều hướng trang giới thiệu; không để liên kết `#` làm người xem hiểu nhầm. Nút tìm kiếm mở tìm mã và danh sách route; không tìm ngôn ngữ tự nhiên toàn hệ thống.

## 4 Use case chính

### UC-D01 Đăng ký đăng nhập

Actor: khách. Nhập tên/email/password xác nhận -> validate -> normalize email -> hash -> tạo user + portfolio VND -> rotate session -> dashboard rỗng. Đăng nhập tài khoản demo đã seed sẽ có dữ liệu, user mới không tự nhận dữ liệu của demo user. Email trùng hoặc password sai báo phù hợp; endpoint auth rate limit. Logout hủy session. MVP demo không yêu cầu SMTP/email verification; link quên mật khẩu chỉ hướng dẫn tài khoản thử, không giả gửi email. Seed password nhận từ local env riêng, không dùng password DB và không ghi trong tài liệu/Git.

### UC-D02 Nạp/rút tiền và đối soát

Actor: thành viên. Chọn `DEPOSIT` hoặc `WITHDRAW` trong Transactions -> validate ownership và cash -> khóa portfolio -> insert transaction immutable -> trả receipt và summary. Form không nhận BUY/SELL, quantity, unit price, fee hoặc tax. Cùng request key/payload chỉ ghi một lần, payload khác cùng key trả 409. Input sai 422, ID user khác 404, lỗi lưu rollback. Lịch sử có thể chứa BUY/SELL do paper execution để đối soát; người dùng không tạo trực tiếp các dòng đó ở form.

### UC-D03 Paper trading và khớp lệnh

Actor: thành viên. Mở Market -> chọn mã cổ phiếu VN/VND -> xem quote board và phiên mô phỏng -> chọn BUY/SELL, Market/Limit, quantity -> server kiểm tra session, available cash/quantity và reservation -> match theo Ask/Bid -> ghi execution và transaction khi khớp -> cập nhật order/portfolio summary. Market BUY ăn Ask, Market SELL ăn Bid; Limit BUY khớp khi Ask <= giá đặt; Limit SELL khớp khi Bid >= giá đặt. Lệnh lớn có thể partial fill. Lệnh mở có thể hủy để giải phóng reservation. Không dùng tiền thật hoặc broker.

### UC-D04 Xem tổng quan

Actor: thành viên. Dashboard tính từ portfolio + transactions + giá mẫu + manual assets; chart giả lập lịch sử trước ngày demo theo fixture, sau giao dịch chart điểm ngày mô phỏng cập nhật. Donut dùng cùng tổng; missing quote có nhãn chưa đủ định giá, không thay bằng 0. Scope lãi/lỗ chỉ chứng khoán, không đổi giá manual asset thành lợi nhuận. Ngày thị trường lấy từ session demo hiện tại, không viết “realtime” hoặc so với ngày thật khi clock khác.

### UC-D05 Mục tiêu và việc hôm nay

Actor: thành viên. Tạo goal(target>0, saved>=0) -> sửa saved -> tính progress; saved chỉ ghi nhận riêng phục vụ tiến độ, không reserve cash, không cộng vào tổng tài sản. Vượt target cho phép, nhãn % có thể>100 nhưng progress bar cap100. Tạo task/đánh dấu xong; bộ đếm chưa xong cập nhật. Không dùng hệ thống goal_allocations dài hạn cho demo.

### UC-D06 Theo dõi thị trường và cảnh báo

Actor: thành viên. Tìm mã -> watch/unwatch -> tạo rule GTE/LTE giá -> “Kiểm tra dữ liệu mô phỏng” lấy quote mới nhất của ngày demo. Khóa rule khi đánh giá; state unknown/false sang true tạo event dạng notification đúng một lần; true lặp không thêm; false rearm. Giá không đổi thì không tự sinh cảnh báo khác. Rule chỉ price trong demo; P/E và tỷ trọng ngành để sau. Không scheduler hoặc email/SMS bắt buộc. Notification giữ observed price/source_date/rule label khi rule xóa.

### UC-D07 Copilot và các chức năng nâng cao

Actor: thành viên. Bấm “Tóm tắt danh mục”, “Tỷ trọng”, “Tiến độ mục tiêu” -> trả lời deterministic bằng phép tính server cùng nguồn. Câu hỏi tự do được lưu lịch sử theo user; ngoài nhóm hỗ trợ -> thông báo chưa hỗ trợ, không generate lời khuyên mua bán. Strategy detail có fixture tĩnh cùng nhãn. Simulation áp dụng shock giá lên q hiện tại, cash/manual giữ nguyên; không mutation DB. Bài học từ fixture versioned, completion keyed bằng lesson slug allowlist. Cộng đồng cho phép đăng/xóa bài theo owner, dùng nội dung mô phỏng không nhắn người thật.

## 5 Quy tắc demo phải giữ

| Mã | Quy tắc |
| --- | --- |
| R01 | Một user một portfolio VND; tất cả truy vấn private giới hạn theo owner, kể cả goal/watchlist/task/alert/progress |
| R02 | Tiền/giá/cost dùng PostgreSQL numeric và decimal server; JSON decimal là chuỗi; không tính số dư bằng JS float |
| R03 | Deposit/withdraw không âm đầu vào; BUY/SELL chỉ qua order; cash/reservation sau execution>=0, vị thế khả dụng>=0; không margin/FX/short |
| R04 | Giá vốn bình quân: BUY thêm gross+fee+tax vào B; SELL giải phóng round8(B*q/Q), bán hết lấy hếtB; realized=net-basis_sold |
| R05 | Lãi tổng=realized+unrealized+net dividend; deposit không là lãi; manual assets/goals không vào P/L này |
| R06 | Đơn vị price riêng VND/share, USD/oz, POINT…; quốc tế có thể xem nhưng giao dịch demo chỉ VN equity VND |
| R07 | Session Market do server điều khiển, UI không cho backdate/sửa trade; seeder tạo lịch sử tăng dần và ghi tổng tiền đầu kỳ bằng deposit |
| R08 | Transaction immutable; order và cash flow có request key/hash; atomic với row lock portfolio/order; không cần revision jobs/reversal engine trong demo |
| R09 | Mọi trang có empty/loading/error và thông báo mô phỏng; số người dùng/rating/testimonials không thể hiện là thành tích đã xác minh |
| R10 | Mật khẩu tài khoản hash; DB password ở local env; không secret ở client, screenshot tài liệu, fixture hoặc Git |
| R11 | Ngày gốc `2026-09-15`; mặc định 5 giây thật = 5 phút mô phỏng; đủ 54 tick tự mở ngày làm việc kế tiếp; server là nguồn sự thật cho tick/revision |
| R12 | Transactions chỉ là cash flow; Market là order flow; chỉ execution khớp mới tạo ledger BUY/SELL |

## 6 Thiết kế giao diện để triển khai

Giữ nền sáng, xanh dương/navy và mint giống ảnh; sidebar desktop, card trắng viền nhạt, green/red cho biến động có thêm dấu +/-. Font đề xuất Be Vietnam Pro hỗ trợ tiếng Việt; fallback sans-serif. Token khởi đầu: primary #087CF0, navy #102A43, background #F3F8FC, text #142338, muted #64748B, positive #149A75, negative #D44B5E. Spacing 4/8/12/16/24/32/48 px; card radius12, button8; không nhét dashboard desktop nguyên vào mobile.

Component: AppShell, PublicHeader/Footer, StatCard, AllocationChart, PortfolioChart, MarketTable, QuoteBoard, OrderTicket, OrderBook, GoalProgress, SummaryPanel, WatchlistTable, AlertList, TaskList, FeatureTile, EmptyState, FormError và DemoBadge. Dữ liệu tất cả chart/quote qua server contract có `as_of`, `is_demo`, `status`; missing riêng NULL, empty riêng 0.

Dashboard 3 cột desktop, 2 cột tablet và 1 cột mobile; sidebar thành drawer có focus/keyboard/close. Bảng cuộn trong container; chart có legend/tooltip và số tổng đọc được không chỉ nhìn màu. Kiểm tra360/768/1366px. Asset hình có thể dùng ảnh licensed phù hợp hoặc placeholder có chủ đích; không coi laptop/robot trong ảnh tham chiếu là file đã sẵn có để nhúng nguyên ảnh website.

## 7 Thứ tự ưu tiên trong hai ngày

P0: login + dữ liệu DB + landing/dashboard sát ảnh + giao dịch làm số liệu đổi. P1: mục tiêu/watchlist/tasks/alerts và lesson progress. P2: trang phụ và trải nghiệm mô phỏng. Giữ mọi menu có đích, nhưng không hy sinh tính đúng P0 để giả vờ đã có backtest/AI thật. Các mốc thời gian là ngân sách dự kiến, không cam kết mọi nghiệp vụ sản phẩm dài hạn hoàn thiện trong 48 giờ.
