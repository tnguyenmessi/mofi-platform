# MOFI Platform

MOFI is a personal finance and investment platform inspired by the provided landing-page and dashboard references. It will help users track assets, monitor markets, plan financial goals, and understand investment data with AI assistance.

## Planned stack

- Laravel and PHP for the backend and business rules
- React, TypeScript, Inertia.js, and Tailwind CSS for the interface
- PostgreSQL hosted by Supabase
- ECharts or Recharts for charts
- GitHub for source control

## Documentation

**Current implementation scope:** [MOFI demo in two days](docs/demo/README.md). This baseline supersedes the earlier long-term MVP proposals for the demo build.

- [Demo screens and use cases](docs/demo/SPECIFICATION.md)
- [Demo database and ERD](docs/demo/DATABASE.md)
- [Demo schedule, tests and handoff](docs/demo/DELIVERY.md)
- [Project overview Word document](docs/word/MOFI_Gioi_thieu_du_an.docx)
- [Use cases and database Word document](docs/word/MOFI_Use_case_va_Database.docx)

- [Requirements and database planning workflow (Vietnamese)](docs/REQUIREMENTS_AND_DATABASE_PLAN.md)

- [Project plan](docs/PROJECT_PLAN.md)
- [Laravel guide](docs/LARAVEL_GUIDE.md)
- [Database design](docs/DATABASE_DESIGN.md)
- [API design](docs/API_DESIGN.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Pre-coding readiness checklist](docs/PRE_CODING_READINESS.md)
- [Architecture decisions](docs/DECISIONS.md)
- [Product scope](docs/PRODUCT_SCOPE.md)
- [Use cases](docs/USE_CASES.md)
- [Business rules](docs/BUSINESS_RULES.md)
- [Metric definitions](docs/METRIC_DEFINITIONS.md)
- [Data dictionary](docs/DATA_DICTIONARY.md)
- [Acceptance scenarios](docs/ACCEPTANCE_SCENARIOS.md)
- [ERD MVP](docs/ERD_MVP.md)
- [Architecture and operations](docs/ARCHITECTURE_AND_OPERATIONS.md)

## Local setup

The Laravel application, React/Inertia interface, migrations, demo seed and tests are in `mofi-app/`. Use `mofi-app/.env.example` as the application template, not the historical root template. Never commit `.env` or credentials.

On 18 September 2026, the PostgreSQL/TLS connection, migrations, application table access controls, demo seed, CI checks and browser demo flows were verified. No real financial activity occurs in the demo.

## Cấu trúc hiện tại

- `mofi-app/`: mã nguồn duy nhất của ứng dụng Laravel (backend, React/Inertia, migrations, seeders, tests).
- `docs/`: tài liệu yêu cầu, use case, database, API, quy trình demo và roadmap.
- `.env.example`: mẫu cấu hình; `.env` thật không được commit.

Ứng dụng dùng kiến trúc Laravel MVC + service layer: Controller nhận request, Form Request xác thực, Policy/middleware phân quyền, Service xử lý nghiệp vụ tài chính, Eloquent Model truy cập PostgreSQL/Supabase, React/Inertia hiển thị giao diện. Đây là modular monolith, phù hợp demo và có thể tách API/worker khi mở rộng.

## Tự chạy demo trên máy

Yêu cầu PHP 8.4+, Composer, Node.js 20+ và PostgreSQL/Supabase.

Trên Windows, nếu lệnh `php` chưa có trong PATH, dùng PHP tại `C:/Users/nguye/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe` hoặc thêm thư mục đó vào PATH.

```powershell
cd mofi-app
Copy-Item .env.example .env
composer install
php artisan key:generate
# điền thông tin PostgreSQL/Supabase vào .env
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Mở `http://127.0.0.1:8000/`. Đăng nhập bằng tài khoản demo trong file local `mofi-app/.local-demo-credentials.md` (file này bị git ignore). Admin dùng `/admin`. Khi phát triển giao diện, chạy thêm terminal thứ hai: `npm run dev`.

Nếu `composer` chưa có trong PATH, cài Composer rồi mở lại PowerShell trước khi chạy các lệnh trên.

Nếu workspace vẫn chậm khi dùng Supabase, đo kết nối và truy vấn trước khi đổi endpoint. Thử nghiệm ngày 18/09 cho thấy Session pooler chậm hơn kết nối trực tiếp trên máy hiện tại, nên vẫn giữ direct + TLS. Cấu hình tùy chọn `DB_EMULATE_PREPARES=true` giảm lượt trao đổi PostgreSQL và đã được bật trong `.env` cục bộ sau kiểm thử; `.env.example` vẫn mặc định `false` để môi trường khác đo trước khi bật. Ứng dụng vẫn dùng tham số truy vấn qua PDO. Không commit `.env` hoặc thông tin đăng nhập. Xem số đo và giới hạn trong `docs/demo/NEXT_ROADMAP.md`.

Kiểm tra trước khi push:

```powershell
php vendor/bin/pint --dirty --format agent
php -d extension=pdo_sqlite vendor/bin/phpunit --colors=never
npx tsc --noEmit
npm run build
```

Không dùng mật khẩu trong README, source code, screenshot hoặc commit. Nếu clone từ GitHub, cần tự tạo `.env` bằng thông tin Supabase của mình.
