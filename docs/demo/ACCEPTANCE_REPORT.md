# MOFI — Báo cáo nghiệm thu demo

Ngày kiểm tra: `21/09/2026`
Phạm vi: bản demo paper-trading; không bao gồm broker thật, tiền thật, KYC/2FA, thanh toán hoặc dữ liệu chứng khoán Việt Nam được cấp phép.

## 1. Kết luận ngắn

Luồng demo chính đã khép kín:

`Bảng giá mô phỏng -> lệnh Market/Limit -> reservation -> execution -> transaction BUY/SELL -> PortfolioSummary -> dashboard`

Luồng tiền mặt được tách riêng:

`Nạp / rút tiền -> transaction DEPOSIT/WITHDRAW`

Trang Transactions không còn tạo BUY/SELL. Lịch sử vẫn hiển thị BUY/SELL đã khớp để đối soát.

## 2. Ma trận T01-T14

| Mã | Nội dung | Trạng thái | Bằng chứng hiện có |
| --- | --- | --- | --- |
| T01 | Public/private routes, menu và auth redirect | Đạt | `routes/web.php`, `WebsiteTest`, `WorkspaceTest`, browser smoke live |
| T02 | Register/login/logout, password hash, session và rate limit | Đạt | `WorkspaceTest`, auth controller, smoke `/login` |
| T03 | Owner isolation và cross-owner access | Đạt | `WorkspaceTest`, `PortfolioSummaryTest`, `PaperTradingTest` |
| T04 | Portfolio formula, cost basis và P/L | Đạt | `DemoDataSeederTest`, `PortfolioSummaryTest`; fixture total assets `38.315.000 VND` |
| T05 | Deposit/withdraw và paper BUY/SELL | Đạt | `TransactionApiTest`, `PaperTradingTest`; cash form chỉ DEPOSIT/WITHDRAW |
| T06 | Negative cash, excess sell, invalid fee và atomic rollback | Đạt | `TransactionApiTest`, `PaperTradingTest` |
| T07 | Idempotency và concurrency | Đạt | PostgreSQL disposable local: `1 test / 18 assertions` ở native prepares và emulated prepares; test paper orders cạnh tranh và duplicate deposit |
| T08 | Goals và progress persistence | Đạt | `WorkspaceTest`, page reload/browser route coverage |
| T09 | Watchlist, tasks, learning progress | Đạt | `WorkspaceTest` với unique/idempotent writes |
| T10 | Alert transition, re-arm và missing price | Đạt | `WorkspaceTest::test_alerts_fire_on_transition_rearm_and_ignore_missing_prices` |
| T11 | Copilot, strategy và simulation boundaries | Đạt | `WorkspaceTest`; deterministic/mock labels; scenario không ghi ledger |
| T12 | Missing data, error states và responsive layout | Đạt có giới hạn | Missing-price tests đạt; live tablet snapshot `770px` không tràn ngang; desktop/mobile breakpoint cần rerun khi có runner viewport cố định |
| T13 | Backend suite, TypeScript, build và secret scan | Đạt | PHPUnit `66 / 65 passed / 1 skipped / 935 assertions`; `tsc --noEmit`; Vite build; `git ls-files` không trả secret files |
| T14 | Supabase permissions, public deploy và debug exposure | Đạt trong phạm vi smoke | Public Railway URL online; private APIs trả `401`; `/does-not-exist` trả `404` không lộ stack trace; public candle/Binance endpoints trả dữ liệu; cấu hình APP_DEBUG chỉ được xác nhận black-box |

## 3. Smoke live đã thực hiện

URL: `https://mofi-platform-demo-production.up.railway.app/`

| Request | Kết quả |
| --- | --- |
| `/up` | HTTP 200 |
| `/` | HTTP 200 |
| `/login` | HTTP 200 |
| `/transactions` khi chưa xác thực | HTTP 302 tới login |
| `/admin` khi chưa xác thực | HTTP 302 tới login |
| `/does-not-exist` | HTTP 404, không thấy `Whoops`, stack trace hoặc `SQLSTATE` |
| `/api/v1/market/live?symbol=BTCUSDT&days=7` | HTTP 200, public Binance preview |
| `/api/v1/instruments/46/candles?days=30` | HTTP 200, OHLC demo của MOFI |
| `/api/v1/instruments/46/market-board` khi chưa xác thực | HTTP 401 |
| `/api/v1/portfolios/1/summary` khi chưa xác thực | HTTP 401 |
| `/api/v1/portfolios/1/transactions` khi chưa xác thực | HTTP 401 |

Browser live `/market` đã hiển thị được MOFI, bảng bid/ask, giá khớp cuối, tham chiếu, trần/sàn, khối lượng, ngày mô phỏng `15/09/2026`, giờ mô phỏng `14:55`, tick `54/54` và trạng thái `Đã đóng cửa`. Snapshot tablet hiện tại `770px` có `document.scrollWidth = 754px`, không tràn ngang; console không có error/warning.

## 4. Trạng thái phiên demo live

- Ngày mô phỏng: `2026-09-15`.
- Nhịp: 5 giây thật tương ứng 5 phút mô phỏng.
- Phiên dùng chung hiện đã đi tới `14:55`, tick `54/54`, trạng thái `CLOSED`.
- Vì phiên live đã đóng, không dùng browser để gửi order/deposit/withdraw. Muốn diễn tập đặt lệnh, dùng database local mới seed hoặc mở lại phiên bằng quy trình vận hành được phê duyệt.

## 5. Giới hạn còn lại trước production

- Chưa có broker adapter, tài khoản chứng khoán, sàn giao dịch, tiền ngân hàng hoặc thanh toán thật.
- Quote Việt Nam, depth và OHLC là fixture/replay; không được gọi là realtime market data.
- Binance chỉ là crypto preview tách biệt, không đi vào danh mục VND.
- Copilot và strategy là deterministic/rule-based; chưa phải LLM hoặc backtest thật.
- Responsive desktop/mobile đầy đủ và monitoring/SLA production cần một vòng kiểm thử riêng với runner viewport và môi trường vận hành cố định.
- Build còn cảnh báo asset runtime cho ba ảnh `/images/journey.jpg`, `/images/mountains.jpg`, `/images/city.jpg`; cần xử lý nếu muốn đóng gói asset tuyệt đối cho CDN.
