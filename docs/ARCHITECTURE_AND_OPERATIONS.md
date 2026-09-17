# MOFI - Kiến trúc, Git và vận hành trước khi code

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

## 1. Ranh giới ứng dụng

```text
React/Inertia UI
  -> Laravel routes/controllers/form requests
  -> policies + application actions
  -> domain services (ledger, valuation, goals, alerts)
  -> Eloquent repositories / PostgreSQL Supabase
  -> jobs + provider adapters sau commit
```

Laravel là nơi duy nhất nhận quyết định tài chính. React chỉ gửi intent và hiển thị metric contract. Supabase Data API không cần expose các bảng nghiệp vụ cho browser; nếu bật trong dashboard, review lại grants/RLS trước khi có dữ liệu.

## 2. Cấu trúc code Laravel dự kiến

```text
app/
  Actions/                 # một write use case, thin orchestration
  Domain/Portfolio/        # ledger, replay, cost basis, valuation
  Domain/Goals/            # earmark invariants
  Domain/Market/           # provider contract, imports, freshness
  Http/Controllers/        # request/response coordination
  Http/Requests/           # input validation, không tính P/L
  Policies/                # owner/admin authorization
  Jobs/                    # after-commit imports, snapshots, alerts
  Models/                  # persistence relationships/casts
  Data/                    # DTO/resources cho decimal strings
resources/js/
  Pages/                   # route pages
  Components/              # cards/charts/tables
  Types/                   # response contracts
tests/Feature/             # auth, ownership, endpoints
tests/Unit/Domain/         # decimal ledger and metric fixtures
```

Không đặt phép tính giá vốn ở React, controller hoặc Eloquent accessor có side effect. Tách `PostTransactionAction`, `ReplayPortfolio`, `CalculateMetrics` và `EvaluateAlert` để test độc lập. Models không tự gọi provider/network.

## 3. Quy ước Git

- `main` là nhánh tích hợp; code đi qua PR khi có collaborator.
- `feature/FR-05-buy-sell`, `fix/BR-09-idempotency`, `docs/erd-review`.
- Commit: `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:` + hiện tại đơn giản.
- PR phải ghi scope/use case, migration/schema impact, lệnh test, screenshot UI và security check.
- Không force-push main; không rewrite history đã được team dùng.
- Tag `schema-v0.1`, `mvp-v0.1` chỉ sau acceptance gate.
- Issue gồm goal, actor, acceptance criteria, out-of-scope, dependencies và sample data.

Repository public chỉ chứa source, fixture giả và tài liệu. Không commit `.env`, database dump, password, API/AI key, cookie, ảnh có dữ liệu cá nhân hoặc log sản xuất. Secret scan chạy trong CI.

## 4. CI tối thiểu trước feature code

CI phải cài PHP/Composer và Node theo version đã ghi, sau đó chạy:

```text
composer validate --strict
vendor/bin/pint --test
php artisan test
npm ci
npm run build
```

Khi có migration nghiệp vụ: chạy PostgreSQL service/test hoặc Supabase branch/development project, `php artisan migrate --force` trên database test, seed fixture, rồi chạy acceptance/domain suite. SQLite không thay cho PostgreSQL vì composite FK, numeric và constraint behavior khác. CI fail nếu phát hiện mẫu secret hoặc test dùng API thật.

## 5. Local commands chuẩn hóa

```text
composer install
copy .env.example .env
php artisan key:generate
npm ci
php artisan migrate --seed       # chỉ khi schema đã được duyệt
php artisan test
npm run dev
```

README phải bổ sung version PHP/Composer/Node/npm, cách tạo `.env`, kiểm tra connection an toàn, cách reset database test và dữ liệu demo. Không đưa password vào command line hoặc shell history; local `.env` đã ignore.

## 6. Supabase runbook

- Project development hiện tại là `mofi-db`, region Sydney. Staging/production nên là project riêng trước khi có dữ liệu thật.
- Quyết định direct/session pooler sau khi kiểm tra IPv4/IPv6, hosting runtime và migration. Direct host có thể yêu cầu IPv6; không chọn mù theo ví dụ.
- TLS bật; `sslmode=require`/driver setting phù hợp môi trường. Không in DSN ra log.
- Laravel connection dùng DB credentials server-side. Chưa dùng Supabase Auth; tránh tạo hai identity source.
- Migration deploy qua CI/release account quyền tối thiểu; app runtime không nên có quyền tạo schema.
- Data API/anonymous key không được dùng cho bảng tài chính. Nếu bật client access sau này, RLS, grants và threat model phải review riêng.
- Backup/restore, retention và free-plan behavior phải được xác minh trong plan hiện tại; không hứa khả năng point-in-time recovery khi chưa kiểm tra.

## 7. Release gates

Gate A: scope/use case/business rules/metric formulas được review. Gate B: ERD/data dictionary/fixture/acceptance được review. Gate C: migration trên PostgreSQL test + policy/security tests pass. Gate D: UI match design reference, responsive/accessibility states pass. Gate E: staging smoke, backup/rollback/runbook và monitoring pass. Không gộp Gate A/B với “đã cài Laravel”.

## 8. Quyết định đang mở

Owner cần điền người duyệt và ngày vào `DECISIONS.md`: EOD provider/licensing, công thức hiệu suất, nhiều tiền tệ, nợ, transfer giữa portfolio, email verification, retention/export/delete và có hay không role support. Khi một quyết định đổi, cập nhật scope/use case/ERD/metric/test cùng PR.
