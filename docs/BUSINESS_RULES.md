# MOFI - Quy tắc nghiệp vụ

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

v0.1 - Dự thảo 17/09/2026. Các quy tắc là phương án nhất quán để review; chưa phải đã được duyệt. Mã BR dùng để truy vết test, schema và use case.

## BR-01 - Ownership

User chỉ đọc/ghi resource của mình. Parent-child phải cùng owner ở cả DB constraints và Laravel policy; đổi ID trên request không được vượt quyền. Role admin chỉ cấp qua thao tác quản trị được kiểm soát, không cho mass assignment. Job luôn mang user scope cần thiết. Laravel không dùng Supabase Auth trong MVP.

## BR-02 - Phạm vi danh mục và tránh cộng trùng

Một user có nhiều portfolio; một cash account thuộc đúng một portfolio. Securities của portfolio nằm trong position projections, cash nằm trong cash entries. Tổng user = tổng cash + tổng securities + manual assets user-level. Không cộng lại portfolio total nếu đã cộng hai thành phần. Manual asset không được đại diện cho cùng lượng cổ phiếu đã ghi trade. Metadata mô tả và màn hình nhập phải cảnh báo nguy cơ ghi trùng; không giả định hệ thống tự nhận biết tài sản vật lý trùng.

## BR-03 - Đầu kỳ và archive

Theo dõi bắt đầu từ mốc user chọn, không suy diễn P/L trước mốc đó. OPENING_CASH là vốn đầu kỳ, không income. OPENING_POSITION chứa quantity và tổng cost basis, không trừ cash vì khoản mua đã xảy ra trước hệ thống. Mỗi account và mỗi portfolio/instrument có tối đa một opening active, phải đứng trước sự kiện khác trong phạm vi đó. Archive portfolio/account chỉ khi tiền = 0, quantity = 0, không goal earmark và không job ghi đang chạy; history không bị xóa.

## BR-04 - Luồng tiền

DEPOSIT/WITHDRAW là external flow. TRANSFER giữa hai account cùng user có tổng cash entries = 0; bên ngoài portfolio nhưng bên trong user là external đối với portfolio, internal đối với user. BUY/SELL, CASH_DIVIDEND là dòng tiền đầu tư, không phải external flow. Không dùng số dư ngoài sổ để nhập mua: cần opening/deposit rõ ràng trước.

## BR-05 - Tiền tệ, số học và thứ tự

MVP tài sản đầu tư VND, không FX; quantity giao dịch cổ phiếu VN là số nguyên > 0, không ép bội số 100 vì nhập lịch sử/lô lẻ. Price > 0, fee/tax >= 0; net sale/income >= 0. Date không tương lai, cùng ngày phải có effective_at và sequence xác định. Dùng decimal arbitrary precision ở domain, không JavaScript float để quyết định số tiền. Tiền VND thanh toán làm tròn half-up đến đồng tại một event; quantity/price lưu 8 số thập phân cho mở rộng, basis dùng 8 số thập phân. API trả decimal dạng chuỗi.

Giới hạn đề xuất trước overflow: quantity <= 10^9, price <= 10^12 VND, amount/basis < 10^20 VND; tổng hợp vượt khả năng kiểu cột bị reject có lỗi, không truncate. Giới hạn phải validate nhất quán ở domain và DB.

## BR-06 - Giá vốn bình quân liên hoàn

BUY: gross = round(q*p); outflow = gross + fee + tax. Q mới = Q cũ + q; B mới = B cũ + outflow. Average = B/Q chỉ là giá trị hiển thị/tính, không là nguồn dữ liệu độc lập. SELL: basis_sold = round8(B*q/Q), riêng q=Q lấy hết B. Net = gross - fee - tax; realized = net - basis_sold; Q và B giảm tương ứng. Phí mua đã nằm trong B, không trừ thêm lần nữa khi tính P/L. Bán quá quantity tại bất kỳ mốc replay nào bị từ chối.

## BR-07 - Income và hành vi chưa hỗ trợ

Cổ tức tiền net = gross - fee - tax; tăng cash và income, không thay đổi Q/B. User ghi tiền thực nhận, không mô phỏng quyền hưởng hay thuế theo luật bằng rate mặc định. Thuế do user nhập rõ; fixture thuế 0 chỉ là giả định test. Splits, cổ tức cổ phiếu, rights, margin, short và settlement T+ chưa hỗ trợ; không âm thầm nhập quote adjusted rồi so với lượng/cost không điều chỉnh. Provider adapter phải khai báo unadjusted/adjusted; MVP chỉ dùng raw/unadjusted cho định giá.

## BR-08 - Audit và correction

Financial event, trade, cash entry và income đã posted bất biến. REVERSAL tham chiếu event gốc, unique; copy cash entries ngược dấu, effective_at gốc, created_at hiện tại. Active timeline loại trade/income/opening đã reversed và bỏ reversal khi tính vị thế. Cash balance tổng signed entries vẫn khớp với active timeline. Replacement là event mới có replaces_event_id, cùng transaction với reversal khi thay thế. Không đảo reversal, không đảo event đã đảo; muốn đổi replacement thì đảo replacement đó.

Replay thứ tự effective_at, replay_sequence, event id; replacement dùng sequence cũ nếu giữ effective_at, để không đổi vị trí mua trước bán cùng thời điểm. Replay chạy toàn bộ suffix bị ảnh hưởng; nếu có âm tiền/quantity trong lịch sử hoặc current earmarks không đủ bảo đảm thì không ghi correction. Không tự sửa lịch sử phụ thuộc. Audit metadata giữ actor, reason và correlation ID, không chứa credential.

## BR-09 - Atomicity, idempotency và concurrency

Lock portfolios theo ID tăng dần, sau đó cash accounts và goals theo ID tăng dần; mọi đường write tài chính/goal/archive phải cùng thứ tự. Transfer/correction lock đầy đủ cả hai phía. Unique(user_id,idempotency_key) + payload hash đảm bảo request retry. Duplicate cùng hash trả receipt cũ; hash khác 409. Ghi header, details, cash entries, audit, projection hiện tại và tăng data_revision trong một DB transaction. Không gửi notification hoặc gọi provider khi đang giữ lock. Worker chạy sau commit, job xử lý theo revision và chỉ publish kết quả nguyên lô; worker chậm không ghi đè revision mới.

## BR-10 - Goal là tiền dành riêng, không phải tài sản mới

Goal allocations lưu số tiền đang earmark theo cash account. Tổng allocations active của account <= balance; spendable = balance - reserved. BUY/WITHDRAW/TRANSFER phải giữ balance sau giao dịch >= reserved. Giảm earmark giải phóng tiền, không ghi cash entry; hoàn thành/archive goal giải phóng allocations và ghi audit trong cùng transaction. Target > 0, allocation >= 0; cho phép overfunded, progress % có thể >100 và progress bar cap 100. Không có công thức ngày hoàn thành dự báo đầu tư ở MVP.

## BR-11 - Tài sản thủ công

Chỉ định giá VND theo ngày và loại tài sản, không tự mô phỏng mua/bán hoặc nguồn tiền. Giá trị gần nhất tại/before ngày định giá được dùng, phải hiển thị ngày người dùng cập nhật. Missing valuation không là 0. Archive có ended_on để giữ lịch sử; từ ngày đó asset không tính vào tổng hiện tại. Mức thay đổi giá trị thủ công không gọi là lợi nhuận; tổng tài sản là gross, chưa trừ nợ. Valuation update có before/after audit.

## BR-12 - Giá trị, snapshot và dữ liệu thiếu

Chỉ dùng quote <= cutoff có currency/unit/type đúng và stream dữ liệu phù hợp. EOD theo ngày giao dịch Việt Nam; calendar provider phải nói rõ ngày mở cửa/ngày lễ, không dùng ngưỡng 24 giờ mù quáng cho cuối tuần. Fresh nếu có giá của phiên hoàn tất gần nhất được kỳ vọng. Không có calendar xác thực thì status freshness_unknown, không khẳng định fresh. Ngoài giờ phiên hôm nay chưa đóng thì dùng phiên trước và ghi as_of.

Missing price làm security/portfolio total NULL (partial) và trả known_value riêng, không lặng lẽ lấy 0. Stale quote có thể dùng để định giá với nhãn stale, không dùng cho cảnh báo actionable. Snapshot gắn trade revision và price revision; trước khi rebuild xong giữ nguyên bản published trước với status rebuilding. Chart không nối dữ liệu mới/cũ như cùng revision.

## BR-13 - Nguồn dữ liệu, public content và giới hạn MVP

Mock/real stream tách bằng providers.is_mock; không mix quotes hoặc lịch sử của hai stream trong một series/P/L. Adapter map instrument + market + source symbol + price unit. Index không phải cổ phiếu giao dịch được. Quote và reference price phải cùng convention/timeframe trước khi tính change. Hết license/rate limit thì ngừng cập nhật và báo stale, không scrape lách điều kiện sử dụng.

Ảnh chỉ là reference; số người dùng/testimonial chưa xác thực không công bố như thành tích thật. AI/sandbox/Pro chưa tích hợp phải ghi trạng thái. Auth/reset có rate limit; log không chứa password, token, connection string hay payload tài chính đầy đủ.

## BR-14 - Alert

PRICE threshold cùng currency/unit instrument; SECTOR_WEIGHT dùng securities value trong portfolio làm mẫu số, không toàn bộ user wealth. Tất cả quotes của scope phải usable/fresh cùng stream; nếu thiếu thì skip. Mock event luôn demo. Rules dùng GTE/LTE với threshold >=0 (weight trong 0..100), trigger false->true, cooldown 24h và policy crossing bị skip như UC-13. Unique(rule_id, observation_key) chống double notification; sửa rule tăng rule version và reset state. Lịch sử event không xóa khi archive rule.

## BR-15 - Data lifecycle và quyền vận hành

Thông tin tài chính không xóa cascade khi xóa nhầm parent. Archive là thao tác mặc định. Yêu cầu export/xóa tài khoản MVP xử lý theo runbook có xác minh; chưa thêm nút xóa vĩnh viễn tự động trước khi chốt retention. Không thu thập dữ liệu thật trước khi retention, quyền admin và quy trình hỗ trợ được chủ sản phẩm xác nhận. Không khẳng định free plan có backup phục hồi theo thời điểm; cần kiểm chứng plan và quy trình backup riêng.
