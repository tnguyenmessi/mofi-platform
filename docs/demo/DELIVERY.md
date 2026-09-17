# Bàn giao bản demo MOFI

## 1 Kế hoạch hai ngày

| Mốc | Công việc và đầu ra | Điều kiện kiểm tra |
| --- | --- | --- |
| Ngày 1 sáng | Setup React/Inertia/TS, DB migrations/seeds, login/register và app shell | Đăng nhập A có dữ liệu; B chỉ thấy dữ liệu B; Laravel query được Supabase |
| Ngày 1 chiều | Landing đầy đủ section, dashboard dùng số liệu thống nhất, donut/line/table | So sánh hai ảnh ở 1366 px; fixture ra W = 38.315.000; mobile không tràn |
| Ngày 2 sáng | Transactions, goals, watchlist, tasks, alerts, lesson progress | Ghi rồi reload còn; cash/Q/P&L đúng; negative và duplicate bị chặn |
| Ngày 2 chiều | AI preview, strategy/simulation/community pages, polish, deploy thử, kiểm thử và diễn tập | Menu không dead end, mock label rõ; chạy script trình bày và backup local nếu hosting lỗi |

Nếu chậm: giữ trang phụ có nội dung rõ ràng và trạng thái giới thiệu, ưu tiên tính đúng giao dịch/DB và hai trang chính. Không âm thầm giảm chức năng đã hứa; ghi chênh lệch vào README nghiệm thu. Chọn local demo ổn định làm phương án dự phòng; chưa chọn hosting trả phí hoặc hứa URL đã tồn tại.

## 2 Luồng trình bày khoảng bảy phút

1. Mở landing: giới thiệu mục đích, stack và tính chất dữ liệu giả.
2. Đăng nhập account demo, chỉ ra các số có cùng nguồn DB và ngày mô phỏng.
3. Mở portfolio và thêm BUY 10 giá 125.000 fee 0 từ fixture gốc. Cash 13.315.000, Q = 160, S = 20 triệu, W = 38.315.000.
4. Reload: giao dịch và chart ngày mô phỏng vẫn còn; thử bán vượt lượng để thấy validation.
5. Tạo goal, thêm watchlist, đánh dấu task; mở notifications sau kiểm tra rule.
6. Mở Copilot và simulation: giải thích cái gì là quy tắc/cái gì là dữ liệu mẫu; shock không thay portfolio.
7. Cho xem ERD, cấu trúc Laravel và Git commit/PR; nói phần sẽ mở rộng sau demo.

## 3 Tiêu chí nghiệm thu

| Test | Tình huống | Expected |
| --- | --- | --- |
| T01 | Tất cả menu public/private | Có route, auth redirect đúng, không dead link/nút thành công giả |
| T02 | Register/login/logout; credentials sai | Hash password; lỗi phù hợp; session rotate và invalidate |
| T03 | A gọi ID portfolio/goal/watchlist của B |404; không ghi hoặc lộ payload; composite FK chặn transaction khác owner |
| T04 | Fixture tính danh mục | Cash 14.565m, W = 38.315m, P/L 3.315m chính xác |
| T05 | Deposit 10 triệu; BUY 10 thêm | Deposit không tăng P/L; BUY đổi cash/Q/chart đúng như DATABASE |
| T06 | SELL vượt lượng, withdraw thiếu tiền, fee âm |422 và không ghi nửa transaction |
| T07 | Cùng request key retry; hai sell đồng thời | Một receipt cùng hash; khác hash trả 409; không vị thế âm |
| T08 | Goal saved 2 triệu / target 10 triệu; sửa saved |20%; reload giữ; cash/W không đổi |
| T09 | Watch duplicate, task toggle, bài học complete | Không duplicate row; trạng thái reload đúng |
| T10 | Alert condition false->true rồi true lặp | Một notification; mark read idempotent; missing price không trigger |
| T11 | Mock market/AI/strategies; scenario shock | Nhãn mô phỏng, không đưa ra kết quả tự nhận là API/LLM/backtest thật; shock không write assets |
| T12 | Empty/missing price, mobile360/tablet768/desktop1366 | Không chia0 hoặc biến missing thành 0; không tràn ngang toàn trang |
| T13 | Build/backend suite/secret scan | PHP domain và feature tests, frontend build qua; .env không tracked; không credentials trong assets |
| T14 | Supabase table permissions và deploy | Unauthenticated Data API không đọc dữ liệu; APP_DEBUG=false trên public URL; login/logout smoke |

T01..T14 là ca dự kiến, chưa chạy vì chưa có tính năng. CI skeleton pass không có nghĩa các ca này pass. Chỉ báo hoàn thành theo bằng chứng command output/manual screenshot trong PR, không tự tick checklist theo tài liệu.

## 4 Git và triển khai

Source hiện ở `mofi-app/`. Docs ở `docs/demo/`, file Word ở `docs/word/`. Dùng feature branch khi bắt đầu code, commit nhỏ theo config/auth/data/UI/feature/test. PR template đã có; CI workflow chưa có, cần tạo với PHP8.4, Node24, Composer install/validate, Pint, tests với PostgreSQL test, npm ci/build và secret check. Chưa có branch protection đã xác minh, không tuyên bố đã bật.

Không dùng `.env` thật trên runner; CI có credential database test tạm. Không dùng lệnh setup có migrate --force trước khi chỉ rõ DB đích. Chỉ seed fake data. Database demo phải ghi rõ read/write demo account, không thu thập thông tin thật; không có payment/API tài chính thật.

Lệnh thực thi sẽ được xác minh khi code: composer install, tạo .env local, key:generate nếu thiếu, npm ci, migrate, seed, test, npm run build. Không tạo thêm APP_KEY mới đè key đang dùng tùy tiện. React/Inertia cần dependency và cấu hình mới, không giả định đã có vì composer Laravel skeleton chạy được.

## 5 Trạng thái trước khi code

| Hạng mục | Trạng thái ngày 17/09/2026 |
| --- | --- |
| Phạm vi demo và database rút gọn | Đã viết trong SPECIFICATION/DATABASE; là baseline triển khai, không phải approval của sếp |
| GitHub repo và scaffold Laravel | Có, đã push từ các đợt trước |
| Mật khẩu local | Đã cấu hình .env bị Git ignore; không có trong tài liệu |
| PostgreSQL direct connection | PDO SELECT read-only qua TLS thành công; public có 0 bảng |
| Laravel-level connection và migrations | Chưa kiểm chứng Laravel DB facade; chưa chạy migrations |
| React/Inertia/TS và giao diện | Chưa cài/chưa triển khai |
| Seed demo, CI và hosting | Chưa tạo; làm ở đợt code nền |
| Supabase grants/Data API | Chưa audit/configure; phải kiểm tra trước expose demo |

Các bước còn lại thuộc thực thi code nền và kiểm thử, không phải thiếu một vòng đặc tả dài hạn nữa. Không cần mật khẩu trong chat thêm lần nữa để viết tài liệu. Secret chỉ dùng server/local khi kết nối được ủy quyền.
