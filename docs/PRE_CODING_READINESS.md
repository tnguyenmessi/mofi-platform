# MOFI Pre-coding Readiness Checklist

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

Mục tiêu của checklist này là xác nhận dự án đủ rõ để bắt đầu code MVP. Mục “đã chuẩn bị” chỉ được đánh dấu khi có tài liệu, người review và tiêu chí kiểm tra; không đánh dấu chỉ vì đã tạo file.

## 1. Product và scope

- [ ] `PRODUCT_SCOPE.md` đã chốt mục tiêu, persona, actor, màn hình và MVP.
- [ ] Mỗi tính năng có mã yêu cầu, mức ưu tiên và tiêu chí nghiệm thu.
- [ ] Có danh sách rõ ràng cho MVP, giai đoạn 2, giai đoạn 3 và ngoài phạm vi.
- [ ] Nội dung/số liệu trong ảnh tham khảo được gắn nhãn minh họa nếu chưa có nguồn xác minh.
- [ ] Có người duyệt scope và ngày/phiên bản duyệt.

## 2. Use case và nghiệp vụ

- [ ] `USE_CASES.md` mô tả actor, tiền điều kiện, luồng chính, ngoại lệ, hậu điều kiện và quyền.
- [ ] `BUSINESS_RULES.md` chốt giá vốn, phí, thuế, cổ tức, nạp/rút, tiền tệ và ngày định giá.
- [ ] `METRIC_DEFINITIONS.md` chốt công thức cho từng thẻ và biểu đồ dashboard.
- [ ] `ACCEPTANCE_SCENARIOS.md` có ca empty state, dữ liệu thiếu, giao dịch trùng và truy cập sai quyền.
- [ ] Có quyết định bằng văn bản cho các câu hỏi còn mở trong `REQUIREMENTS_AND_DATABASE_PLAN.md`.

## 3. Database và dữ liệu

- [ ] ERD đã review và mọi quan hệ truy vết được về use case.
- [ ] Data dictionary có cột, kiểu, precision/scale, nullability, default, constraint và index.
- [ ] Dữ liệu gốc, snapshot, cache và dữ liệu dẫn xuất được phân biệt.
- [ ] Mọi bảng theo user có quy tắc ownership và authorization.
- [ ] Money/price/quantity dùng `numeric`, không dùng float cho tính toán tài chính.
- [ ] Có chiến lược timezone, ngày giao dịch, ngày giá và nguồn dữ liệu.
- [ ] Có migration order, rollback strategy và seed data nhất quán.
- [ ] Đã xác định backup/restore và quy trình không chạy lệnh phá dữ liệu trên production.

## 4. Kiến trúc ứng dụng

- [ ] Chốt Laravel là application boundary và React/Inertia là UI boundary.
- [ ] Chốt nơi đặt domain services, actions, policies, form requests và DTO/resource.
- [ ] Chốt authentication, role/permission, session, CSRF, rate limit và audit log.
- [ ] Chốt `MarketDataProvider` interface và mock provider trước nhà cung cấp thật.
- [ ] Chốt queue, scheduler, cache, notification và xử lý lỗi.
- [ ] Chốt API/page props, validation response và quy ước lỗi.
- [ ] Chốt quy tắc không đưa database/AI/market secrets ra browser.

## 5. Git và quy trình review

- [ ] GitHub repository public đã có `main` bảo vệ khi team bắt đầu cộng tác.
- [ ] Không commit `.env`, database password, API key, dump dữ liệu hoặc file build lớn.
- [ ] Branch feature dùng dạng `feature/<name>`, bug dùng `fix/<name>`, docs dùng `docs/<name>`.
- [ ] Commit dùng Conventional Commits: `feat:`, `fix:`, `docs:`, `test:`, `chore:`.
- [ ] Mọi thay đổi đi qua pull request; PR có mục đích, ảnh hưởng schema, test và screenshot nếu là UI.
- [ ] Có CODEOWNERS/reviewer và template issue/PR.
- [ ] Có CI chạy PHP lint, Pint, test, npm build và kiểm tra secrets.
- [ ] Có tag/release cho các mốc schema và MVP.

## 6. Môi trường phát triển

- [ ] README có lệnh cài đặt từ máy mới.
- [ ] `.env.example` có tên biến, mô tả nguồn và giá trị an toàn mẫu.
- [ ] Phiên bản PHP, Composer, Node và npm được khóa hoặc ghi rõ.
- [ ] Có script bootstrap, migrate, seed, test và dev server.
- [ ] Có PostgreSQL/Supabase development project riêng staging/production.
- [ ] Laravel có thể kết nối database bằng credential local mà không in secret ra log.
- [ ] Có seed user demo và dữ liệu dashboard nhất quán với công thức.

## 7. Supabase và vận hành

- [ ] Project name, ref, region, plan và chủ sở hữu được ghi trong tài liệu không nhạy cảm.
- [ ] Connection mode (direct/session/transaction pooler), IPv4/IPv6 và TLS đã được kiểm tra.
- [ ] Password/API keys lưu trong secret manager hoặc `.env` local, không lưu Git.
- [ ] Xác định Laravel có truy cập trực tiếp PostgreSQL; Data API không expose bảng tùy tiện.
- [ ] Xác định RLS/DB privileges phù hợp nếu Data API hoặc client trực tiếp được bật.
- [ ] Có backup, migration history, logs, health check và cảnh báo lỗi.

## 8. UI và design handoff

- [ ] Có sitemap cho landing, auth, dashboard và các trang MVP.
- [ ] Có design tokens: màu, typography, spacing, radius, trạng thái success/warning/error.
- [ ] Có component inventory và quy tắc responsive desktop/tablet/mobile.
- [ ] Mỗi chart có empty/loading/error/stale-data state.
- [ ] Nội dung tiếng Việt, định dạng tiền và timezone được thống nhất.
- [ ] Có accessibility checklist: keyboard, contrast, label, focus, screen reader cơ bản.

## 9. Bảo mật và dữ liệu tài chính

- [ ] Threat model tối thiểu cho account, portfolio, import giá và AI context.
- [ ] Authorization được kiểm tra ở backend cho mọi resource id.
- [ ] Dữ liệu nhạy cảm được giảm thiểu, log không chứa password/token/financial payload thừa.
- [ ] Có audit trail cho giao dịch, điều chỉnh và hành động admin.
- [ ] Có rate limit cho login, import, AI và endpoints đắt tiền.
- [ ] Có chính sách xóa/tải dữ liệu và retention phù hợp với scope thử nghiệm.
- [ ] UI ghi rõ đây là công cụ theo dõi/giáo dục nếu chưa có giấy phép tư vấn đầu tư.

## 10. Cổng bắt đầu code

Chỉ bắt đầu code MVP khi:

1. Scope và use case đã được review.
2. ERD, data dictionary và metric formulas đã được chốt.
3. Supabase connection đã được kiểm tra trên môi trường development.
4. Migration/seed plan và acceptance scenarios đã sẵn sàng.
5. Branch/PR/CI và quy tắc secrets đã hoạt động.
6. Có issue đầu tiên đủ nhỏ để hoàn thành, review và kiểm thử.

Nếu một mục chưa đạt, ghi rõ blocker và quyết định tạm thời trong `DECISIONS.md`; không âm thầm đổi nghiệp vụ trong lúc dựng UI.
