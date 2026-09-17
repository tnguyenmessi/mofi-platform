# MOFI - Phạm vi sản phẩm

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

Phiên bản: 0.1 - 17/09/2026. Trạng thái: dự thảo có thể review; chưa được chủ sản phẩm/sếp phê duyệt. Các mặc định nghiệp vụ dưới đây được đề xuất để thiết kế nhất quán, không phải sự thật suy ra từ ảnh.

## 1. Mục tiêu

MOFI là website tiếng Việt giúp nhà đầu tư cá nhân ghi nhận tài sản, xem tiền và danh mục, theo dõi thị trường, đặt mục tiêu và hiểu số liệu. Hai ảnh tham chiếu xác định bố cục landing page và dashboard. MOFI MVP là công cụ ghi nhận/theo dõi, chưa phải sàn giao dịch.

Persona chính là nhà đầu tư cá nhân tự nhập giao dịch chứng khoán Việt Nam. Persona phụ là người theo dõi tài sản gia đình và mục tiêu tiết kiệm. Mỗi tài khoản có dữ liệu riêng; chưa hỗ trợ tài sản dùng chung giữa nhiều người.

## 2. Tác nhân và quyền

| Tác nhân | Quyền |
| --- | --- |
| Khách | Xem landing, đăng ký, đăng nhập, yêu cầu đặt lại mật khẩu |
| Thành viên đã xác minh email | Quản lý danh mục, tiền, giao dịch, mục tiêu, watchlist, cảnh báo và việc của mình |
| Quản trị viên | Quản lý mã tài sản, nguồn giá, nội dung và xem trạng thái tác vụ; không mặc định xem tiền/giao dịch của thành viên |
| Scheduler/worker | Nhập giá, tính snapshot, đánh giá cảnh báo trong phạm vi job được cấp |
| Provider giá | Cung cấp dữ liệu giá có nguồn và thời điểm; không truy cập danh mục người dùng |

Tài khoản chưa xác minh chỉ được dùng màn hình xác minh, hồ sơ và đăng xuất. AI, người đăng chiến lược và người kiểm duyệt cộng đồng là tác nhân của giai đoạn sau.

## 3. Phạm vi MVP có thể nghiệm thu

| Yêu cầu | Chức năng/nguồn ảnh | Use case | Ca nghiệm thu |
| --- | --- | --- | --- |
| FR-01 | Landing, giới thiệu tính năng, CTA đăng ký, footer | UC-01 | AT-01 |
| FR-02 | Tài khoản, hồ sơ, cài đặt, đăng xuất | UC-02 | AT-02, AT-03 |
| FR-03 | Tạo danh mục và tài khoản tiền, số dư đầu kỳ | UC-03 | AT-04 |
| FR-04 | Nạp/rút và chuyển tiền nội bộ | UC-04 | AT-05, AT-06 |
| FR-05 | Ghi mua/bán cổ phiếu, giá vốn và phí/thuế | UC-05, UC-06 | AT-07, AT-08, AT-09 |
| FR-06 | Cổ tức tiền, sửa sai bằng nghiệp vụ đảo/thay thế | UC-07, UC-08 | AT-10, AT-11 |
| FR-07 | Tài sản thủ công ngoài danh mục đầu tư | UC-09 | AT-12 |
| FR-08 | Các thẻ tổng tài sản, tiền mặt, đầu tư, P/L và biểu đồ | UC-10 | AT-13, AT-14, AT-15 |
| FR-09 | Mục tiêu: mua nhà, học cho con, quỹ dự phòng | UC-11 | AT-16 |
| FR-10 | Bảng thị trường và watchlist | UC-12 | AT-17 |
| FR-11 | Cảnh báo giá và tỷ trọng, lịch sử thông báo | UC-13 | AT-18 |
| FR-12 | Việc hôm nay, chuông thông báo, tìm mã | UC-14 | AT-19 |
| FR-13 | Import giá và metadata mã có kiểm soát | UC-15 | AT-20 |
| FR-14 | Tóm tắt danh mục dựa trên quy tắc | UC-16 | AT-21 |

Mọi tính năng ở bảng phải hoạt động với dữ liệu thử nhất quán. Market provider đầu tiên là mock có nhãn; có mock không đồng nghĩa đã tích hợp dữ liệu chứng khoán thật. Thanh tìm kiếm chỉ tìm mã và điều hướng trang MVP, chưa tìm kiếm ngôn ngữ tự nhiên.

## 4. Giới hạn đề xuất cho bản đầu

- Nhập tay, tiền tệ VND, long-only, hạch toán ngay, không số dư tiền/số lượng âm.
- Nhiều danh mục, mỗi danh mục có nhiều tài khoản tiền; mỗi tài khoản tiền thuộc đúng một danh mục. Không dùng chung một tài khoản tiền giữa hai danh mục.
- Giao dịch cổ phiếu Việt Nam; mã quốc tế/hàng hóa/crypto chỉ được hiển thị tham khảo khi có bộ dữ liệu ghi rõ đơn vị, không đưa vào tài sản VND để cộng trực tiếp.
- Tài sản thủ công (ví dụ bất động sản, vàng vật chất) dùng giá trị do người dùng nhập; nằm ngoài P/L chứng khoán. Không ghi cùng tài sản ở cả vị thế và tài sản thủ công.
- Mục tiêu dùng tiền được dành riêng từ tài khoản tiền. Không phân bổ cổ phiếu hoặc tạo lãi suất dự báo tự động ở MVP.
- Biểu đồ lịch sử theo ngày (EOD); các mốc 1W/1M/3M/1Y/All chỉ hiển thị dữ liệu thực có. Chưa có intraday thì không hiển thị nút 1D như đã hỗ trợ.
- Chỉ hiển thị P/L tiền và tỷ lệ P/L trên giá vốn còn giữ; chưa công bố TWR/CAGR/Sharpe của danh mục.

## 5. Giai đoạn tiếp theo và ngoài phạm vi

| Mốc | Chức năng |
| --- | --- |
| Giai đoạn 2 | Provider giá thật có giấy phép; AI Copilot thật; nội dung và tiến độ học đầu tư; dữ liệu cơ bản doanh nghiệp và cảnh báo P/E |
| Giai đoạn 3 | Strategy Studio, backtest, Investment Lab, giao dịch ảo, cộng đồng, follow chiến lược, thanh toán/Pro |
| Chưa cam kết | Nhiều tiền tệ và FX, nợ phải trả, liên kết ngân hàng/chứng khoán, lệnh thật, margin, bán khống |

Các tile tính năng chưa làm phải ghi “Sắp có” hoặc chuyển đến trang giới thiệu trạng thái; không có nút giả báo thành công. Không tạo schema chi tiết cho toàn bộ giai đoạn sau khi chưa có use case.

## 6. Trang và trạng thái

Public: `/`, `/products`, `/pricing` (nội dung dự kiến, chưa thanh toán), các trang giới thiệu/chính sách, login/register/reset. Private: dashboard, portfolios, accounts, transactions, manual-assets, goals, market, watchlist, alerts, tasks, notifications, settings. Trang admin metadata/import tách quyền riêng.

Landing dùng nội dung tĩnh trước, không cần CMS hoặc bảng testimonials. Số 100.000 người dùng, 4,9/5 và lời chứng thực trong ảnh là nội dung minh họa; chỉ công bố như thành tích thật khi có bằng chứng và quyền sử dụng. Không suy ra một hệ thống quản trị nội dung chỉ từ ảnh.

## 7. Yêu cầu phi chức năng và tiêu chí kết thúc MVP

- Tiền chính xác bằng decimal; mọi write tài chính atomic, có idempotency, audit và ownership.
- Có empty/loading/error/stale states; giao diện dùng keyboard được, bảng có caption/heading, màu tăng giảm có dấu/text đi kèm.
- Kiểm tra ở chiều rộng 360, 768, 1366 và 1920 px; bảng có vùng cuộn riêng, không làm toàn trang tràn ngang.
- Mục tiêu kỹ thuật dự kiến: p95 dữ liệu dashboard dưới 1 giây với 10.000 sự kiện/người, 50 vị thế và snapshot một năm trên môi trường staging đã ghi cấu hình; phải đo, chưa phải SLA đã đạt.
- Không gửi credential/payload tài chính vào log công khai; không nhận secret trong Git hoặc PR.
- Hoàn tất MVP khi AT-01..AT-24 đạt, chart khớp fixture, lỗi quyền đã kiểm tra và môi trường đích đã xác minh. Đạt test skeleton chưa đủ.

## 8. Những việc cần duyệt

Chủ sản phẩm cần xác nhận: phạm vi theo dõi bằng VND; mô hình tài khoản-danh mục; mục tiêu dành riêng tiền mặt; phương pháp giá vốn; nguồn giá mock ở demo; AI/học/backtest/Pro ở mốc sau. Theo dõi tại [sổ quyết định](DECISIONS.md). Có thể review từng nhóm, không cần đợi code mới phát hiện khác biệt.
