# MOFI - Kế hoạch đặc tả nghiệp vụ và thiết kế database

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

Trạng thái: Đề xuất để trao đổi và chốt phạm vi; chưa phải đặc tả đã duyệt hoặc schema triển khai.

Tài liệu dựa trên hai ảnh tham chiếu: website giới thiệu MOFI và dashboard sau đăng nhập. Nội dung trong ảnh là tham khảo sản phẩm, không phải yêu cầu kỹ thuật bắt buộc. Những quyết định không thể suy ra từ ảnh được ghi là đề xuất hoặc câu hỏi cần chốt.

## 1. Mục tiêu và thứ tự thực hiện

Mục tiêu là chuyển hai ảnh thành bộ yêu cầu có thể kiểm chứng, sau đó thiết kế PostgreSQL phù hợp trước khi tạo migration nghiệp vụ.

```text
Đọc màn hình và xác định phạm vi
    -> Viết use case và quy tắc nghiệp vụ
    -> Định nghĩa chỉ số và nguồn dữ liệu
    -> Vẽ ERD và viết từ điển dữ liệu
    -> Kiểm tra bằng tình huống mẫu, chốt thiết kế
    -> Viết migration, kiểm thử trên database riêng
    -> Triển khai lên project Supabase dành cho MOFI
```

Laravel skeleton và project Supabase đã có. Chưa có schema nghiệp vụ MOFI. Thông số host đã được điền nhưng kết nối database chưa được xác minh; mật khẩu local hiện chưa được cấu hình theo lần kiểm tra trước. Không xem các bài test skeleton là kiểm chứng nghiệp vụ tài chính hoặc kết nối Supabase.

## 2. Đối chiếu giao diện với phạm vi sản phẩm

MVP là phiên bản đầu có chức năng hoạt động thực tế. Prototype là bản minh họa giao diện bằng dữ liệu mẫu, không được mô tả như chức năng đã tích hợp thật.

| Khu vực trong ảnh | Chức năng cần đặc tả | Dữ liệu cần xác định | Đề xuất phạm vi |
| --- | --- | --- | --- |
| Landing: header, hero, giới thiệu sản phẩm, footer | Xem giới thiệu, điều hướng, đăng ký | Nội dung, hình ảnh, liên kết | MVP; nội dung tĩnh trước |
| Landing: số người dùng, đánh giá, nhận xét | Hiển thị bằng chứng về sản phẩm | Số liệu được xác minh, nội dung được phép sử dụng | Prototype ghi rõ minh họa; không công bố số giả như số thật |
| Đăng ký, đăng nhập, avatar, cài đặt | Quản lý tài khoản và hồ sơ | Người dùng, vai trò, phiên đăng nhập | MVP |
| Tiền & Tài sản; các thẻ tổng tài sản, tiền mặt | Ghi nhận tài khoản tiền và tài sản | Dòng tiền, số dư, tài sản thủ công | MVP |
| Đầu tư; giá trị danh mục và lãi/lỗ | Tạo danh mục, ghi nhận mua/bán, xem vị thế | Giao dịch, giá vốn, phí, thuế, định giá | MVP, nhập tay |
| Phân bổ tài sản theo loại/ngành | Tổng hợp tỷ trọng | Phân loại tài sản, ngành, giá trị đã quy đổi | MVP; ngành chỉ áp dụng tài sản có ngành |
| Biểu đồ danh mục 1D/1W/1M/3M/1Y/All | Xem lịch sử giá trị và hiệu suất | Lịch sử giá, giao dịch, snapshot, dòng tiền ngoài | MVP theo ngày; 1D intraday để sau nếu chưa có dữ liệu |
| Mục tiêu: mua nhà, học cho con, quỹ dự phòng | Tạo mục tiêu, phân bổ tiền, theo dõi tiến độ | Mục tiêu, hạn, khoản phân bổ | MVP |
| Thị trường Việt Nam/thế giới/hàng hóa/crypto | Xem chỉ số và giá thị trường | Instrument, nguồn giá, đơn vị, thời điểm dữ liệu | Mock trước; tích hợp giá cuối ngày thị trường Việt Nam trước |
| Watchlist | Thêm/xóa mã, xem giá và xu hướng | Danh sách theo dõi và lịch sử giá | MVP |
| Cảnh báo của tôi | Đặt ngưỡng, nhận và xem lịch sử cảnh báo | Điều kiện, lần kích hoạt, trạng thái, thông báo | MVP cho ngưỡng giá/tỷ trọng; P/E để sau |
| Việc nên làm hôm nay | Tạo việc, đánh dấu hoàn thành | Nội dung việc, ngày đến hạn, trạng thái | MVP, việc do người dùng nhập trước |
| MOFI AI, ô hỏi đáp, tóm tắt hôm nay | Giải thích dữ liệu danh mục, lưu hội thoại | Thông tin đã tính, nguồn dữ liệu, hội thoại | Giai đoạn 2; MVP có tóm tắt theo quy tắc, ghi nhãn rõ |
| Học đầu tư | Xem khóa/bài học, theo dõi tiến độ | Nội dung, khóa học, bài học, tiến độ | Giai đoạn 2 |
| Strategy Studio | Tạo chiến lược và đánh giá CAGR/Sharpe/drawdown | Phiên bản chiến lược, tham số, chuỗi lợi nhuận | Giai đoạn 3 |
| Investment Lab, sân tập ảo, Backtest | Mô phỏng kịch bản/giao dịch ảo | Tài khoản ảo, bộ dữ liệu, lần chạy, kết quả | Giai đoạn 3, tách khỏi tài sản thực |
| Cộng đồng & Chiến lược | Công bố, theo dõi, khám phá chiến lược | Nội dung công khai, lượt theo dõi, kiểm duyệt | Giai đoạn 3 |
| Bảng giá và MOFI Pro | Xem gói, cấp quyền tính năng, thanh toán | Gói, quyền, thuê bao, sự kiện thanh toán | Giai đoạn 3; không xây thanh toán ở MVP |
| Tìm kiếm toàn cục, chuông thông báo | Tìm mã/mục tiêu; đọc thông báo | Phạm vi tìm kiếm, lịch sử thông báo | MVP giới hạn theo quyền người dùng |

Các tính năng ở giai đoạn sau vẫn nằm trong định hướng tổng thể. Không tạo sẵn tất cả bảng chỉ vì chúng xuất hiện trên dashboard.

## 3. Bước 1 - Chốt phạm vi và tác nhân

### Công việc

- Viết mục tiêu sản phẩm, đối tượng người dùng và giá trị của phiên bản đầu.
- Lập danh mục màn hình công khai, màn hình đăng nhập và trang quản trị tối thiểu.
- Gán mã yêu cầu cho từng tính năng trong bảng đối chiếu.
- Tách dữ liệu nhập tay, dữ liệu tính toán, dữ liệu nhà cung cấp và dữ liệu minh họa.
- Ghi rõ chức năng hoạt động thật, prototype và chức năng chưa triển khai.

### Tác nhân đề xuất

| Tác nhân | Vai trò |
| --- | --- |
| Khách | Xem landing, thông tin sản phẩm, đăng ký/đăng nhập |
| Thành viên | Quản lý dữ liệu tài chính riêng, theo dõi thị trường và mục tiêu |
| Quản trị viên | Quản lý danh mục mã và nguồn dữ liệu, nội dung, trạng thái hệ thống; không mặc định được xem toàn bộ tài sản cá nhân |
| Tác vụ nền | Nhập giá, định giá danh mục, tạo snapshot, đánh giá cảnh báo |
| Nhà cung cấp giá | Cung cấp dữ liệu thị trường theo hợp đồng/API |
| Dịch vụ AI | Nhận ngữ cảnh được cho phép và trả lời ở giai đoạn tích hợp AI |

### Đầu ra và điều kiện hoàn tất

`PRODUCT_SCOPE.md`: danh sách màn hình, yêu cầu, mức ưu tiên, ngoài phạm vi và giả định. Hoàn tất khi các bên thống nhất MVP và không còn nhầm lẫn giữa demo và chức năng thật.

## 4. Bước 2 - Viết use case và quy tắc nghiệp vụ

### Danh sách use case khởi đầu

| Mã | Use case | Tác nhân chính |
| --- | --- | --- |
| UC-01 | Đăng ký, xác minh email, đăng nhập, khôi phục mật khẩu | Khách/thành viên |
| UC-02 | Cập nhật hồ sơ và thiết lập tài khoản | Thành viên |
| UC-03 | Tạo tài khoản tiền và danh mục đầu tư | Thành viên |
| UC-04 | Ghi nhận số dư đầu kỳ, nạp, rút và chuyển tiền | Thành viên |
| UC-05 | Ghi nhận mua tài sản | Thành viên |
| UC-06 | Ghi nhận bán một phần/toàn bộ tài sản | Thành viên |
| UC-07 | Ghi nhận cổ tức tiền, phí và điều chỉnh sai sót | Thành viên |
| UC-08 | Ghi nhận tài sản thủ công và cập nhật định giá | Thành viên |
| UC-09 | Xem tổng quan, phân bổ, lịch sử tài sản và lãi/lỗ | Thành viên |
| UC-10 | Tạo mục tiêu và phân bổ tiền cho mục tiêu | Thành viên |
| UC-11 | Quản lý watchlist và xem thị trường | Thành viên |
| UC-12 | Tạo, tạm dừng và xem lịch sử cảnh báo | Thành viên |
| UC-13 | Quản lý việc cần làm và thông báo | Thành viên |
| UC-14 | Nhập giá, kiểm tra dữ liệu, tính lại định giá | Tác vụ nền/quản trị viên |
| UC-15 | Xem tóm tắt danh mục có giải thích nguồn số liệu | Thành viên |

Mỗi use case phải có: mục tiêu, actor, tiền điều kiện, trigger, dữ liệu đầu vào, luồng chính, luồng thay thế, ngoại lệ, hậu điều kiện, quyền truy cập, quy tắc liên quan và tiêu chí nghiệm thu. Use case nhiều hành động như UC-01 cần tách thành các đặc tả con khi viết chi tiết.

### Ví dụ định hướng cho UC-05 - Ghi nhận mua cổ phiếu

1. Thành viên chọn danh mục, tài khoản tiền, mã cổ phiếu, ngày, số lượng, giá và phí.
2. Hệ thống kiểm tra quyền sở hữu, mã hợp lệ, số dương và tiền khả dụng.
3. Hệ thống ghi giao dịch mua và dòng tiền liên quan trong cùng một database transaction.
4. Hệ thống cập nhật phép chiếu vị thế/giá vốn; giao dịch gốc là dữ liệu có thể kiểm toán.
5. Dashboard hiển thị giá trị được tính từ giá thị trường gần nhất kèm thời điểm giá.

Ngoại lệ phải mô tả: thiếu tiền, gửi lặp thao tác, mã ngừng cung cấp giá, giao dịch có ngày trong quá khứ, phí không hợp lệ và danh mục của người khác. Thiếu giá không đồng nghĩa giá bằng 0; vẫn lưu được giao dịch hợp lệ nhưng đánh dấu định giá chưa đầy đủ.

### Các quyết định nghiệp vụ cần chốt

| Vấn đề | Đề xuất ban đầu | Tác động thiết kế |
| --- | --- | --- |
| Nhập dữ liệu | Nhập tay, chưa liên kết ngân hàng hoặc công ty chứng khoán | Không có lệnh giao dịch tiền thật |
| Tiền tệ | MVP giao dịch bằng VND; hỗ trợ bảng giá quốc tế theo nguyên tệ để tham khảo | Chưa cộng giá USD vào tổng tài sản VND nếu không có FX |
| Giá vốn | Bình quân gia quyền liên hoàn; phí mua cộng vào giá vốn | Cần thứ tự giao dịch ổn định và quy tắc tính lại |
| Giới hạn giao dịch | Không bán khống, không margin, không âm tiền trong MVP | Kiểm tra đồng thời số dư và số lượng |
| Thanh toán giao dịch | MVP ghi nhận ngay tại thời điểm người dùng nhập giao dịch | Chưa mô phỏng lịch thanh toán T+ và tiền chờ về |
| Số dư đầu kỳ | Ghi rõ số lượng, giá vốn và mốc bắt đầu theo dõi | Không suy diễn hiệu suất trước thời điểm có dữ liệu |
| Cổ tức | Cổ tức tiền được ghi nhận; chia tách/cổ tức cổ phiếu thiết kế riêng trước khi hỗ trợ | Tránh giá vốn/số lượng sai sau sự kiện doanh nghiệp |
| Chỉnh sửa lịch sử | Ghi nhận điều chỉnh/đảo giao dịch có liên kết và tính lại từ mốc ảnh hưởng | Không xóa mất dấu vết giao dịch đã hạch toán |
| Mục tiêu tài chính | Phân bổ từ tài sản/tiền đã có; không tạo thêm tài sản | Tránh cộng số tiền mục tiêu vào tổng tài sản lần thứ hai |
| Giá thị trường | Dữ liệu mẫu khi dựng UI, sau đó EOD nếu chọn được nguồn | Ghi nguồn, thời điểm giá, thời điểm nhập và độ mới |
| Nợ phải trả | Chưa hỗ trợ ở MVP; nhãn là tổng tài sản, chưa gọi là tài sản ròng | Khi bổ sung nợ cần mô hình và công thức riêng |
| Tài sản thủ công | Người dùng cập nhật giá trị tại các mốc ngày | Phải phân biệt tăng định giá với dòng tiền mua thêm |

Các đề xuất này là cơ sở để viết bản đầu; chưa phải quyết định đã được người dùng/sếp phê duyệt.

Đầu ra: `USE_CASES.md` và `BUSINESS_RULES.md`. Hoàn tất khi có luồng lỗi và quy tắc số liệu đủ để viết ca kiểm thử, không chỉ có luồng thành công.

## 5. Bước 3 - Định nghĩa dữ liệu cho từng chỉ số

Đầu ra: `METRIC_DEFINITIONS.md`. Mỗi chỉ số phải ghi công thức, phạm vi tài sản, tiền tệ, đơn vị, thời điểm chốt, nguồn dữ liệu, quy tắc làm tròn và hành vi khi thiếu dữ liệu.

| Chỉ số | Định nghĩa đề xuất cần làm rõ |
| --- | --- |
| Tiền mặt | Tổng số dư tài khoản tiền trong phạm vi được chọn, không cộng lại tiền đã nằm trong số khác |
| Danh mục đầu tư | Tổng số lượng vị thế nhân với giá hợp lệ tại thời điểm định giá; nêu rõ có bao gồm tiền mặt hay không |
| Tổng tài sản | Tiền mặt + chứng khoán/đầu tư + tài sản thủ công không trùng lặp |
| Lãi/lỗ chưa thực hiện | Giá trị thị trường vị thế còn giữ trừ giá vốn còn lại |
| Lãi/lỗ đã thực hiện | Tiền bán ròng sau phí/thuế trừ giá vốn phần đã bán |
| Lãi/lỗ tổng | Đã thực hiện + chưa thực hiện + thu nhập đầu tư ròng; không tính phí/thu nhập hai lần |
| Lãi/lỗ hôm nay | Chênh lệch giá trị trong ngày sau điều chỉnh tiền nạp/rút ngoài phạm vi; thống nhất mốc phiên trước |
| Tỷ suất sinh lời | Chốt phương pháp theo nhu cầu; không lấy tăng tổng tài sản làm tỷ suất khi có nạp/rút. TWR cần xử lý thời điểm dòng tiền; MVP có thể chỉ hiển thị P/L tiền và giá trị |
| Tỷ trọng tài sản/ngành | Giá trị nhóm chia giá trị tập tài sản làm mẫu số; phải công bố mẫu số và nhóm không phân loại |
| Tiến độ mục tiêu | Giá trị phân bổ hợp lệ / số tiền mục tiêu; xác định cách xử lý vượt 100% |
| Thay đổi giá thị trường | So với giá tham chiếu theo thị trường, kèm thời điểm; không mặc định mọi thị trường có cùng phiên |

### Không dùng số liệu trên ảnh làm chuẩn nghiệm thu

Ảnh dashboard ghi tổng tài sản 3,42 tỷ, tiền mặt 420 triệu và danh mục 2,68 tỷ. Hai phần sau cộng lại là 3,10 tỷ: còn thiếu giải thích cho 320 triệu. Có thể là tài sản khác, nhưng không thể kết luận từ ảnh. Tỷ trọng phân bổ cũng cần được tính lại từ cùng một bộ dữ liệu. Khi dựng demo, phải tạo dữ liệu nhất quán thay vì chép độc lập từng con số.

## 6. Bước 4 - Thiết kế ERD và từ điển dữ liệu

### Thiết kế mô hình khái niệm trước

| Nhóm nghiệp vụ | Thực thể ứng viên | Điều cần chứng minh |
| --- | --- | --- |
| Danh tính | User, Profile, Role | Mọi dữ liệu cá nhân có chủ sở hữu và chính sách truy cập |
| Tiền và danh mục | CashAccount, Portfolio, CashMovement | Quan hệ tiền/danh mục rõ ràng; chuyển nội bộ không tăng tài sản |
| Danh mục mã | Instrument, AssetClass, Sector, Market | Mã + thị trường/định danh là duy nhất; đơn vị giá và tiền tệ rõ |
| Lịch sử đầu tư | Trade, Adjustment, IncomeEvent | Có thể tái dựng vị thế, tiền và giá vốn |
| Tài sản thủ công | ManualAsset, ManualValuation | Có lịch sử giá trị và nguồn định giá |
| Dữ liệu thị trường | DataProvider, PriceObservation | Chống nhập trùng và giữ đúng thời điểm giá |
| Kết quả tính | PositionProjection, PortfolioSnapshot | Dữ liệu dẫn xuất có thể xây dựng lại; biết nguồn và thời điểm tính |
| Mục tiêu | FinancialGoal, GoalAllocation | Tiền phân bổ không tự sinh thêm tài sản, không phân bổ trùng vượt mức |
| Theo dõi | Watchlist, WatchlistItem, AlertRule, AlertEvent | Quy tắc cảnh báo tách khỏi lịch sử kích hoạt |
| Công việc | Task, Notification | Có trạng thái, người sở hữu, thời điểm hoàn thành/đọc |

Đây là thực thể ứng viên, chưa phải danh sách bảng đã chốt. Cần quyết định mô hình sổ giao dịch/sổ tiền đủ đơn giản cho MVP nhưng vẫn tái dựng được số liệu; không tạo bảng `transactions` đa năng thiếu ràng buộc chỉ để chứa mọi loại sự kiện.

### Nội dung bắt buộc của bản thiết kế chi tiết

- ERD có cardinality 1-1, 1-n, n-n và giải thích quan hệ.
- Từ điển dữ liệu: cột, ý nghĩa, kiểu, precision/scale, nullability, default, ví dụ và nguồn.
- PK, FK, unique/check constraints, index và quy tắc xóa/lưu trữ.
- Các ràng buộc cùng chủ sở hữu giữa tài khoản, danh mục và giao dịch; không chỉ kiểm tra ở giao diện.
- Chính sách concurrency, idempotency và tính nguyên tử khi ghi giao dịch cùng dòng tiền.
- Phân biệt dữ liệu gốc và cache/snapshot; định nghĩa quy trình xây lại khi sửa lịch sử.
- PostgreSQL `numeric` cho tiền/giá/số lượng, truyền decimal qua API phù hợp; không dùng float cho phép tính tiền.
- Lưu timestamp có timezone và xác định ngày giao dịch theo thị trường; phân biệt ngày giá với thời điểm tải dữ liệu.
- Lược đồ triển khai cho Laravel: quyền DB tối thiểu cần thiết, schema, migrations và nơi lưu bí mật.
- Vì Laravel là backend duy nhất, không cần expose bảng nghiệp vụ qua Supabase Data API. Kiểm tra và tắt expose hoặc thiết lập quyền/RLS phù hợp trước khi có bảng/dữ liệu; không giả định Laravel policies bảo vệ được API Supabase độc lập.
- Lựa chọn direct connection hoặc session pooler dựa trên IPv6/IPv4 thực tế, TLS và khả năng chạy migration; host điền trong `.env` chưa chứng minh kết nối hoạt động.

Đầu ra: `DATABASE_DESIGN.md` viết lại đầy đủ, `DATA_DICTIONARY.md` và ERD Mermaid có thể đưa vào tài liệu Word. Hoàn tất khi từng trường và quan hệ đều truy vết về use case/quy tắc, và mọi widget MVP có nguồn dữ liệu rõ ràng.

## 7. Bước 5 - Kiểm tra thiết kế trước migration

Tạo bộ tình huống có đầu vào, kết quả mong đợi, công thức và thực thể chịu ảnh hưởng trong `ACCEPTANCE_SCENARIOS.md`.

| Tình huống | Kết quả cần chứng minh |
| --- | --- |
| Tài khoản mới chưa có tài sản | Dashboard có empty state; không có phần trăm chia cho 0 |
| Mua nhiều lần, giá và phí khác nhau | Giá vốn bình quân và số dư tiền đúng |
| Bán một phần | Lãi đã thực hiện và giá vốn còn lại đúng |
| Nạp thêm 10 triệu | Tổng tài sản tăng nhưng không tự ghi nhận 10 triệu lợi nhuận |
| Chuyển tiền giữa hai tài khoản của cùng người | Tổng tài sản không đổi, có cặp bút toán liên kết |
| Nhận cổ tức tiền | Tăng tiền và thu nhập đúng một lần |
| Giá cũ/không có giá/ngày nghỉ | Hiển thị độ mới hoặc trạng thái thiếu; không lấy giá 0 |
| Sửa giao dịch của tháng trước | Tính lại phần lịch sử bị ảnh hưởng và giữ dấu vết |
| Bấm lưu hai lần hoặc bán đồng thời | Không ghi trùng, không vượt số lượng/tiền khả dụng |
| Một khoản tiền phân bổ cho nhiều mục tiêu | Không vượt số dư được phân bổ, không nhân đôi tài sản |
| Người A truy cập ID dữ liệu của người B | Từ chối trên backend và không rò dữ liệu qua API khác |
| Giá vượt ngưỡng nhiều lần nhập dữ liệu | Không spam cảnh báo; có chính sách tái kích hoạt |

Ví dụ số để kiểm chứng: mua 100 cổ phiếu giá 100.000 VND, phí 10.000 VND; mua tiếp 100 giá 120.000 VND, phí 10.000 VND. Tổng giá vốn 22.020.000 VND, bình quân 110.100 VND/cổ phiếu. Bán 50 giá 130.000 VND, phí 10.000 VND, giả sử thuế bằng 0 chỉ cho ca này: tiền bán ròng 6.490.000 VND; giá vốn phần bán 5.505.000 VND; lãi đã thực hiện 985.000 VND; còn 150 cổ phiếu với giá vốn 16.515.000 VND. Ca thực tế có thuế phải có đầu vào và phép tính riêng.

Hoàn tất khi đã rà soát với người phụ trách sản phẩm, ghi lại các quyết định và giải quyết câu hỏi có thể thay đổi schema MVP. Các yêu cầu giai đoạn sau có thể để mở với phạm vi trì hoãn rõ ràng.

## 8. Bước 6 - Kế hoạch migration sau khi chốt

1. Viết migration theo dependency của ERD đã thống nhất; rà soát constraints và các index.
2. Tạo factory/seed dữ liệu giả nhất quán với các công thức; không dùng tài khoản hay số liệu tài chính thật.
3. Chạy trên PostgreSQL dành riêng cho kiểm thử, kiểm tra migration và các tình huống nghiệp vụ. SQLite đơn thuần không thay thế kiểm tra hành vi PostgreSQL.
4. Kiểm tra schema/quyền/Data API trên môi trường Supabase đích và kết nối từ Laravel.
5. Review thay đổi, chạy migration lên môi trường phát triển MOFI, đối chiếu schema thực tế với ERD và ghi kết quả.
6. Chỉ sau đó nối dashboard với dữ liệu thật của ứng dụng; mock market provider vẫn có thể dùng khi chưa chọn nhà cung cấp.

Không chạy `migrate:fresh` lên database đang có dữ liệu cần giữ. Xác định môi trường và khả năng phục hồi trước những migration thay đổi dữ liệu.

## 9. Bộ tài liệu bàn giao

| Tài liệu | Nội dung | Thứ tự |
| --- | --- | --- |
| PRODUCT_SCOPE.md | Mục tiêu, actor, màn hình, MVP và lộ trình | 1 |
| USE_CASES.md | Luồng chính/ngoại lệ, phân quyền, tiêu chí nghiệm thu | 2 |
| BUSINESS_RULES.md | Quy tắc tiền, giá vốn, mục tiêu, cảnh báo | 2 |
| METRIC_DEFINITIONS.md | Công thức, nguồn và thời điểm dữ liệu cho dashboard | 3 |
| DATABASE_DESIGN.md + DATA_DICTIONARY.md | ERD, cột, constraints, index, quyền và dữ liệu dẫn xuất | 4 |
| ACCEPTANCE_SCENARIOS.md | Các tình huống và kết quả mong đợi | 5 |
| DEPLOYMENT.md | Hướng dẫn kết nối, kiểm thử và triển khai migration | 6 |

Hai tài liệu Word dự kiến, tạo sau khi nội dung được rà soát:

1. **MOFI - Giới thiệu dự án và phạm vi sản phẩm:** đối tượng, vấn đề, giải pháp, hai nhóm màn hình, công nghệ, MVP và lộ trình; phục vụ trình bày với sếp.
2. **MOFI - Đặc tả use case và thiết kế dữ liệu:** use case diagram, đặc tả luồng, quy tắc, công thức, ERD, từ điển dữ liệu và tình huống kiểm chứng; phục vụ triển khai/review kỹ thuật.

Tài liệu Markdown trong Git là nguồn nội dung để cập nhật và truy vết; Word là bản trình bày có ngày/phiên bản. Đợt công việc này mới lập kế hoạch, chưa tạo các tài liệu đầu ra còn lại hoặc migration nghiệp vụ.

## 10. Những điểm cần quyết định ở buổi chốt đầu tiên

1. MVP có chấp nhận nhập tay, VND và định giá cuối ngày như đề xuất không?
2. Bản đầu hỗ trợ tài sản thủ công đến mức nào: tiền gửi, vàng, bất động sản và tài sản khác?
3. Có cần nhiều danh mục/tài khoản và chuyển tiền nội bộ ngay từ đầu không?
4. AI, học đầu tư, cộng đồng, mô phỏng và Pro ưu tiên ở mốc nào? Tính năng nào bắt buộc hoạt động khi demo?
5. Dữ liệu thị trường cần nguồn thật ngay hay có thể dùng mock có nhãn trong bản đầu?

Có thể tiếp tục viết bản đặc tả dự thảo theo các đề xuất trên; các câu trả lời thay đổi nghiệp vụ phải được phản ánh vào ERD trước khi triển khai migration.
