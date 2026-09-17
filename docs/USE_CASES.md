# MOFI - Đặc tả use case MVP

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

Phiên bản 0.1, dự thảo review. Phạm vi và mã FR tại [PRODUCT_SCOPE](PRODUCT_SCOPE.md); quy tắc BR tại [BUSINESS_RULES](BUSINESS_RULES.md); AT là ca thiết kế, chưa phải test đã chạy.

## Quy ước áp dụng cho mọi use case

Private use case yêu cầu phiên hợp lệ, email đã xác minh và kiểm tra owner tại backend. ID của người khác trả 404; thiếu quyền admin trả 403. Validation dùng 422; session/CSRF hết hạn yêu cầu đăng nhập/làm mới, không tự gửi lại write. Ghi tài chính phải có request key; cùng key khác payload là 409; cùng key cùng payload trả kết quả cũ. Mọi thất bại ghi dữ liệu phải rollback toàn bộ, không để nửa giao dịch. Bảng thuật ngữ: “sự kiện” là hành động tài chính gốc; “dòng tiền” là thay đổi số dư; “vị thế” là số lượng và giá vốn tính lại được.

## UC-01 - Xem giới thiệu và bắt đầu sử dụng

- FR-01; actor: khách; trigger: truy cập trang chủ; tiền điều kiện: không có.
- Input: đường dẫn và CTA. Luồng: tải nội dung -> xem tính năng -> bấm đăng ký hoặc đăng nhập -> đến đúng form; người đã đăng nhập có nút vào dashboard.
- Thay thế: tính năng tương lai mở trang giới thiệu có nhãn trạng thái; thị trường mẫu ghi demo.
- Lỗi: dữ liệu bảng giá chưa tải không làm hỏng nội dung trang; hiển thị unavailable thay vì giá 0.
- Hậu điều kiện: chưa tạo user/giao dịch chỉ do truy cập. BR-13; AT-01.

## UC-02 - Tài khoản và hồ sơ

- FR-02; actor: khách/thành viên; trigger: đăng ký/login/reset/cập nhật profile.
- UC-02a: nhập tên, email, password và xác nhận -> normalize email -> validate -> tạo user role member và profile VND/Asia_Ho_Chi_Minh -> gửi xác minh. Không tạo demo assets vào tài khoản thật.
- UC-02b: đăng nhập -> kiểm tra credential -> rotate session -> kiểm tra email verified -> dashboard/verify notice. Đăng xuất invalidate session và CSRF token.
- UC-02c: yêu cầu reset luôn trả thông báo chung -> gửi link một lần có hạn -> người dùng đổi password -> thu hồi session cũ. Không log token.
- UC-02d: cập nhật display name; MVP giữ base currency VND. Đổi email cần xác minh lại; không cho client gửi role để nâng quyền.
- Lỗi: email trùng/credential sai/rate limit; reset hết hạn hoặc dùng lại bị từ chối. Payload tài khoản không trả password hash.
- Hậu điều kiện: user chỉ xem dữ liệu mình, ngày tạo có audit. BR-01, BR-13; AT-02, AT-03.

## UC-03 - Tạo danh mục, tài khoản tiền và nhập đầu kỳ

- FR-03; actor: thành viên; tiền điều kiện: đã đăng nhập; trigger: thêm danh mục/tài khoản.
- Input: tên danh mục, tên tài khoản, ngày bắt đầu, số dư tiền đầu kỳ; với vị thế đầu kỳ thêm mã, số lượng và tổng giá vốn.
- Luồng: tạo portfolio -> tạo cash account VND thuộc portfolio -> ghi OPENING_CASH nếu số dư > 0. Tùy chọn OPENING_POSITION ghi số lượng/giá vốn ban đầu, không trừ tiền lần thứ hai.
- Thay thế: tạo rỗng hợp lệ; thêm tài khoản vào danh mục có sẵn; mỗi instrument chỉ có một lần nhập đầu kỳ cho mỗi portfolio trước khi phát sinh giao dịch mã đó.
- Lỗi: ngày tương lai, số dư âm, danh mục khác owner hoặc đã archive; nhập đầu kỳ sau giao dịch của cùng phạm vi bị từ chối.
- Hậu điều kiện: số dư đầu kỳ có event để truy vết; không ghi trực tiếp holding. Archive chỉ khi không còn vị thế, tiền, phân bổ đang dùng; dữ liệu lịch sử vẫn đọc được. BR-02, BR-03; AT-04.

## UC-04 - Nạp, rút, chuyển tiền

- FR-04; actor: thành viên; tiền điều kiện: tài khoản active cùng owner.
- Input: loại, tài khoản nguồn/đích, số tiền, effective_at, note, request key.
- Luồng: validate -> lock accounts và các goal earmarks theo ID tăng dần -> kiểm tra replay và tiền chưa dành riêng -> ghi event + cash entries -> tăng revision dữ liệu -> đánh dấu snapshot cần tính lại.
- Chuyển nội bộ ghi một event, hai entries âm/dương bằng nhau trong cùng transaction; tài khoản nguồn và đích phải khác nhau. Có thể chuyển khác portfolio của cùng user.
- Lỗi: không đủ tiền chưa dành riêng; request trùng; concurrent write; ngày trước mốc đầu kỳ hoặc làm số dư lịch sử âm.
- Hậu điều kiện: transfer tổng tài sản user không đổi; deposit/withdraw là dòng tiền ngoài, không phải P/L. BR-04, BR-10; AT-05, AT-06.

## UC-05 - Ghi nhận mua cổ phiếu

- FR-05; actor: thành viên; tiền điều kiện: tài khoản và danh mục active, instrument VN equity active, VND.
- Input: mã, portfolio/account, số lượng nguyên dương, giá, phí, thuế, ngày và request key.
- Luồng: kiểm tra ownership và ngày -> lock portfolio/accounts -> tính tổng thanh toán -> replay ứng viên để kiểm tra tiền tự do -> ghi BUY event, trade và cash entry âm -> cập nhật projection/revision -> trả receipt và số dư mới.
- Thay thế: giá mua do user nhập khác quote vẫn hợp lệ; thiếu quote không cản lưu giao dịch nhưng market value là missing/partial.
- Lỗi: không đủ tiền, account không thuộc portfolio, mã khác currency, fee âm; duplicate key không tạo thêm event.
- Hậu điều kiện: vị thế tăng, phí/thuế mua vào giá vốn, tiền giảm đúng bằng số thanh toán. BR-05, BR-06, BR-09; AT-07, AT-09.

## UC-06 - Ghi nhận bán cổ phiếu

- FR-05; actor: thành viên; tiền điều kiện: có vị thế đủ lượng tại mốc hiệu lực.
- Luồng: nhập giống UC-05 với SELL -> replay/lock -> tính giá vốn phần bán -> ghi trade, cash entry tiền bán ròng -> cập nhật vị thế/P/L -> trả receipt.
- Thay thế: bán hết tiêu thụ toàn bộ cost basis còn lại để không lưu sai số thừa. Phí + thuế không được vượt tiền bán gộp.
- Lỗi: bán quá lượng, hai request bán đồng thời, correction khiến lịch sử âm. Không tạo position âm.
- Hậu điều kiện: realized P/L được ghi trong projection, không tạo thêm một cash entry cho “lãi”. BR-05, BR-06; AT-08, AT-09.

## UC-07 - Ghi nhận cổ tức tiền

- FR-06; actor: thành viên; trigger: đã nhận tiền cổ tức thực tế.
- Input: mã, account thuộc portfolio, ngày, gross amount, fee, tax; nhập gross và các khoản khấu trừ, không nhập lại net.
- Luồng: tính net -> ghi CASH_DIVIDEND + income event + entry dương -> cập nhật income/P/L.
- Thay thế: đã bán hết cổ phiếu trước ngày nhận vẫn được nhập; chưa tính quyền hưởng tự động.
- Lỗi: net < 0, mã hoặc owner sai, duplicate. Hậu điều kiện: tăng tiền và income đúng một lần, không tăng giá vốn. BR-07; AT-10.

## UC-08 - Đảo/thay thế giao dịch sai

- FR-06; actor: owner; tiền điều kiện: event chưa bị đảo, còn đầy đủ dependency và dữ liệu để replay.
- Input: event gốc, lý do bắt buộc, nội dung thay thế tùy chọn, request key.
- Luồng: lock toàn bộ portfolio/account bị ảnh hưởng -> dựng active timeline bỏ event gốc và thêm event thay thế -> kiểm tra mọi số dư tại mọi mốc, goal allocation hiện tại -> nếu hợp lệ, lưu REVERSAL liên kết event gốc, cash entries đối dấu và replacement; audit -> tăng revision -> tính lại từ ngày cũ nhất bị ảnh hưởng.
- Giữ effective_at của reversal như event gốc; created_at lưu lúc thực hiện sửa. Trade/income gốc còn nguyên để audit nhưng bị loại khỏi active replay. Replacement giữ vị trí thứ tự logic của event cũ nếu cùng timestamp.
- Lỗi: đảo lần hai, đảo một reversal, thiếu lý do, bỏ mua làm bán sau đó vượt lượng hoặc bỏ nạp làm thiếu tiền -> từ chối toàn bộ, nêu dependency để user xử lý. Không cascade sửa ngầm giao dịch khác.
- Hậu điều kiện: không DELETE/UPDATE giá trị event gốc; mọi biểu đồ đang rebuild có nhãn, không trộn revisions. BR-08, BR-09; AT-11.

## UC-09 - Theo dõi tài sản thủ công

- FR-07; actor: thành viên; input: tên, loại, VND, ngày/giá trị định giá, ghi chú nguồn.
- Luồng: tạo asset -> ghi valuation đầu -> thêm valuation mới theo ngày khi cập nhật -> dashboard lấy valuation gần nhất không sau mốc báo cáo.
- Thay thế: valuation cùng ngày cập nhật có audit; archive ghi ended_on và loại khỏi giá trị kể từ ngày đó. Gỡ tài sản không tự cộng tiền vào account; UI nhắc ghi nhận dòng tiền riêng nếu có.
- Lỗi: giá trị âm, ngày ngoài vòng đời, owner sai. Hậu điều kiện: không tính P/L hay TWR từ thay đổi valuation thủ công, tránh hiểu nhầm lợi nhuận. BR-11; AT-12.

## UC-10 - Xem dashboard và danh mục

- FR-08; actor: thành viên; input: scope danh mục/all, khoảng ngày.
- Luồng: lấy projections/snapshots đúng revision -> tổng hợp tiền, securities, manual assets theo định nghĩa -> biểu đồ allocation/lịch sử -> trả dữ liệu kèm currency, as_of và data status.
- Thay thế: người mới có số dư 0 và empty charts; không đủ giá dùng partial kèm số tiền đã định giá, không gọi đó là tổng đầy đủ. Ngoài khoảng theo dõi không tự vẽ số.
- Lỗi: rebuild chưa xong hoặc import lỗi -> trạng thái rõ ràng, giữ bản cũ có timestamp; không âm thầm trộn cũ/mới.
- Hậu điều kiện: đọc không phát sinh giao dịch. BR-12; M-01..M-09; AT-13..AT-15.

## UC-11 - Mục tiêu và tiền dành riêng

- FR-09; actor: thành viên; input: tên, loại, target VND > 0, deadline, cash account và amount dành riêng.
- Luồng: tạo goal -> lock account -> tăng/giảm earmark -> kiểm tra tổng earmarks active không vượt cash balance -> ghi audit -> hiển thị tiến độ.
- Thay thế: nhiều account cùng goal và một account nhiều goal; giảm target dưới allocated chỉ tạo overfunded state; progress label có thể >100%, thanh dừng ở 100%.
- Tăng target/đổi ngày không dự báo lợi nhuận. Hoàn thành/đóng goal phải giải phóng earmarks trong cùng transaction; không tự chuyển tiền.
- Lỗi: buy/withdraw/transfer làm cash balance < reserved bị chặn, cần giải phóng trước. Hậu điều kiện: tổng tài sản không đổi khi dành riêng tiền. BR-10; AT-16.

## UC-12 - Thị trường, tìm mã và watchlist

- FR-10; actor: thành viên; input: query mã/tên, watchlist, instrument.
- Luồng: tìm trong catalog -> thêm vào watchlist -> trả quote, đơn vị, % thay đổi (nếu có giá tham chiếu hợp lệ), sparkline và nhãn mock/stale.
- Thay thế: mã chưa có giá hiển thị unavailable; xóa item không xóa catalog/quote; chỉ thị trường có nguồn dữ liệu mới hiện dữ liệu.
- Lỗi: item trùng -> trả item sẵn có; watchlist owner sai -> 404. BR-13; AT-17.

## UC-13 - Cảnh báo

- FR-11; actor: thành viên và worker; input: PRICE hoặc SECTOR_WEIGHT, comparator GTE/LTE, threshold, instrument hoặc portfolio/sector.
- Luồng: tạo rule -> worker nhận observation/version mới -> đánh giá điều kiện -> tạo event/notification đúng một lần -> cập nhật rule state.
- Mặc định: chỉ kích hoạt ở lần chuyển false -> true; rule mới đã true phát một lần. Sau đó cần false để rearm và cooldown ít nhất 24 giờ. Nếu crossing xảy ra trong cooldown, bỏ qua crossing đó và chờ lần false -> true tiếp theo.
- Lỗi: quote thiếu/cũ, mock lẫn real, revision chưa hoàn thành -> không đánh giá, ghi skipped reason; paused rule không phát. Chỉnh điều kiện reset state có audit.
- Hậu điều kiện: event giữ observed value, timestamp và source key; notification in-app, chưa gửi email/SMS đầu tư. BR-14; AT-18.

## UC-14 - Việc cần làm và thông báo

- FR-12; actor: thành viên; input: title, due_date, complete/uncomplete; notification mark-read.
- Luồng: tạo việc -> hôm nay gồm việc đến hạn và quá hạn chưa xong -> đánh dấu -> updated state. Notifications có read_at, đánh dấu đã đọc idempotent.
- Không tự thêm “FPT ra báo cáo” khi chưa có feed sự kiện; MVP việc do user nhập.
- Lỗi: owner sai; title quá dài/rỗng; ngày hiển thị theo Việt Nam. BR-01; AT-19.

## UC-15 - Catalog và nhập giá

- FR-13; actor: admin/worker; tiền điều kiện: mock provider hoặc provider đã được phép sử dụng.
- Luồng: admin tạo asset class/market/instrument/sector mapping -> job tạo import run -> provider adapter chuẩn hóa -> validate từng record -> upsert đúng provider/instrument/time -> ghi correction revision nếu khác payload -> enqueue định giá lại và cảnh báo.
- Lỗi: timeout/429 retry hữu hạn, bad currency/price/unit đưa vào error count; không xóa quote cũ; không log API key. Job lặp không nhân bản quote/notification.
- Không gọi provider trên mỗi request dashboard. Hậu điều kiện: run có số accepted/rejected và trạng thái. BR-13; AT-20.

## UC-16 - Tóm tắt danh mục

- FR-14; actor: thành viên; tiền điều kiện: có metric contract và dữ liệu cùng revision.
- Luồng: chọn các câu có căn cứ (P/L ngày, tỷ trọng ngành, độ mới giá) -> gắn metric, thời điểm và phạm vi -> hiển thị cùng dashboard.
- Thay thế: thiếu giá hoặc chưa có dữ liệu thì giải thích thiếu, không bịa nhận định. Tóm tắt do quy tắc phải ghi rõ, không mang nhãn chatbot AI đang hoạt động.
- Lỗi: mismatch revision -> bỏ tóm tắt và báo đang cập nhật. Hậu điều kiện: không sinh khuyến nghị mua/bán, không gửi tài sản ra dịch vụ AI ở MVP. AT-21.
