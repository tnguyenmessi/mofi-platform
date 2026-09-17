# MOFI - Tình huống nghiệm thu thiết kế

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

v0.1 - Các ca bên dưới là đặc tả expected result, CHƯA chạy trên ứng dụng vì chưa có implementation. F1 là fixture ở [METRIC_DEFINITIONS](METRIC_DEFINITIONS.md). Tax 0 của trade trong F1 chỉ phục vụ test. Khi viết code cần test PostgreSQL cùng policy và domain, không thay bằng screenshot đơn thuần.

| ID / liên kết | Tiền điều kiện và thao tác | Kết quả mong đợi | Cấp kiểm tra |
| --- | --- | --- | --- |
| AT-01 / FR-01 UC-01 | Khách mở landing và CTA; xem tile tương lai | CTA đến form; tính năng chưa có ghi rõ; không công bố người dùng/review giả | Browser 360/1366px |
| AT-02 / FR-02 UC-02 | Đăng ký, email trùng khác hoa/thường, login sai nhiều lần; verify/reset hết hạn và dùng lại | Email unique normalized; generic login/reset message; rate limit; token hết hạn/đã dùng bị từ chối; logout vô hiệu session | Feature auth |
| AT-03 / FR-02 BR-01 | A đọc/sửa ID portfolio, goal, notification của B; tự gửi role=admin; thử FK child sang parent B | HTTP 404/403 phù hợp, không đổi dữ liệu; composite FK reject direct cross-owner insert; không lộ payload | Feature + PostgreSQL |
| AT-04 / FR-03 UC-03 | Tạo rỗng; opening cash 1 triệu và opening 10 cổ phiếu cost 900.000; nhập opening lần hai | Cash 1 triệu, basis 900.000, không trừ cash lại; opening trùng/sau trades bị reject; không có lịch sử trước mốc | Domain + DB |
| AT-05 / FR-04 UC-04 | Từ F1 sau dividend, deposit thêm 10 triệu, quote giữ nguyên | Cash 24.565.000, V 43.315.000; total P/L 3.315.000, P/L khoảng nạp=0 | Domain exact decimal |
| AT-06 / FR-04 UC-04 | Chuyển 5 triệu A->B cùng user; giả lập lỗi giữa write | Hai entries -5m/+5m; user wealth không đổi; portfolio external flow ±5m; lỗi rollback cả hai; account khác owner bị từ chối | Transaction + concurrency |
| AT-07 / FR-05 UC-05 | Deposit 30m, mua hai lô F1 | Q=200, B=22.020.000, cash=7.980.000, avg=110.100; fee đã nằm trong B | Domain exact |
| AT-08 / FR-05 UC-06 | Bán 50 @130.000, fee10.000 sau AT-07, tax0 | Net6.490.000; basis_sold5.505.000; realized985.000; Q150; B16.515.000; cash14.470.000 | Domain exact |
| AT-09 / FR-05 UC-05/06 | Gửi cùng key hai lần; đổi payload cùng key; hai request bán 150 đồng thời khi còn 200 | Một receipt cho duplicate; 409 cho payload khác; chỉ một sell150 thành công, Q50; request thất bại không ghi nửa cash | Parallel integration |
| AT-10 / FR-06 UC-07 | Dividend gross100.000 tax5.000 sau AT-08 | Cash14.565.000; net_income95.000; Q/B không đổi; M-06 tăng đúng95.000 | Domain exact |
| AT-11 / FR-06 UC-08 | Đảo một BUY khiến SELL phía sau thiếu lượng; sau đó thử đảo SELL50 hợp lệ trong F1 | Trường hợp thiếu lượng rollback, giữ nguyên sổ. Đảo SELL: Q200, B22.020.000, cash8.075.000 (có dividend), realized0. Event gốc còn, reversal unique, snapshot rebuild; đảo lại bị reject | Replay + DB |
| AT-12 / FR-07 UC-09 | Thêm manual asset5m; valuation lên6m ngày sau; ended_on ngày tiếp theo | W tăng5m rồi1m, không biến thành P/L chứng khoán; trước started_on không có; từ ended_on không cộng asset; không tự cộng cash khi archive | Domain dates |
| AT-13 / FR-08 UC-10 | F1 quote125.000 và manual5m; xem dashboard | S18.750.000; U2.235.000; R985.000; I95.000; V33.315.000; W38.315.000; không cộng goal/P&L thêm vào W | Response contract |
| AT-14 / FR-08 UC-10 | Portfolio rỗng; Q>0 nhưng quote mất; quote cũ; ngày nghỉ với calendar; calendar thiếu | Empty không chia0; total NULL+known_value khi partial; stale/unknown đúng nhãn; không giá0 hay giả fresh | Domain + UI states |
| AT-15 / FR-08 UC-10 | Correction trong quá khứ, job run revision N kết thúc sau run N+1 | N không được publish đè N+1; chart published một revision; rebuilding hiển thị rõ; gap không nội suy | Worker integration |
| AT-16 / FR-09 UC-11 | Cash14.565.000, earmark2m cho target10m; thêm earmark đồng thời; rút vượt tiền tự do | Progress20%, spendable12.565.000, W không đổi; tổng earmarks<=balance; withdraw vượt bị reject; đóng goal giải phóng; 12m/10m label120% bar100% | Domain + locks + UI |
| AT-17 / FR-10 UC-12 | Thêm cùng mã hai lần vào cùng watchlist; xem USD/oz, POINT, VND/share | Một item; đúng đơn vị; thiếu ref => N/A change; xóa item không xóa quote; mã USD không được ghi trade VND | Feature/catalog |
| AT-18 / FR-11 UC-13 | Rule>=100, observations90->110->115->90->110; crossings trong/ngoài cooldown; retry same version/key | First crossing một event;115 không thêm; crossing trong cooldown skip, cần false->true mới; duplicate không thêm notification; stale/missing skip | Worker/domain |
| AT-19 / FR-12 UC-14 | Tạo task hôm nay/quá hạn, complete/uncomplete, mark notification read hai lần | Due theo VN; completed được loại khỏi chưa xong; read idempotent; owner khác404 | Feature |
| AT-20 / FR-13 UC-15 | Provider timeout, giá âm/sai currency, duplicate, quote correction cùng time | Retry hữu hạn; invalid reject; giữ quote cũ; duplicate không thêm; correction tăng quote/provider revision và rebuild; không log key | Adapter contract |
| AT-21 / FR-14 UC-16 | Summary từ F1, sector allocation; làm giá partial | Mọi câu có metric/as_of và mock label; partial không bịa kết luận; không gọi AI thật | Contract/UI |
| AT-22 / BR-05,06 | Mua3 giá100, tổng cost301; bán1 rồi2 | basis_sold100,33333333 và200,66666667; cuối Q0/B0; tổng cost giải phóng301; không floating residue | Decimal unit |
| AT-23 / BR-09 | Trade thiếu account, negative fee, event detail sai shape; request hợp lệ lỗi trước commit | DB checks/domain reject đúng lớp; không orphan header/cash entry; direct owner FK fail; notification chỉ sau commit | PostgreSQL integration |
| AT-24 / NFR | Dashboard/landing 360,768,1366,1920px, keyboard; dataset10k events/50positions/1year | Không tràn toàn trang; chart có fallback text; loading/error/empty; đo p95 với môi trường ghi rõ, không tự đánh dấu đạt | Browser/performance |

## Điều kiện chạy kiểm thử triển khai

PostgreSQL test riêng, dữ liệu giả và credential tạm; không chạy migrate:fresh lên Supabase dùng chung. Test suite financial chạy độc lập provider mạng; fixed clock và deterministic quote calendar. Tests provider dùng mock responses có schema và replay. Test concurrency dùng hai DB connections thực, không coi hai lần gọi tuần tự là concurrency test.

PR ghi test name, lệnh đã chạy, kết quả và ảnh cho UI; không gắn trạng thái “passed” cho những ca chỉ mới được tính tay. Migration dry-run/rollback là cổng sau review thiết kế, không là yêu cầu phải chạy migration nghiệp vụ ngay trong giai đoạn tài liệu.
