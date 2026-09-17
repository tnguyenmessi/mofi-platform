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

The Laravel skeleton is in `mofi-app/`; the MOFI application features and React/Inertia setup are not implemented yet. Use `mofi-app/.env.example` as the application template, not the historical root template. Never commit `.env` or credentials.

On 17 September 2026, a read-only PDO PostgreSQL connection using the local configuration and required TLS succeeded, with zero tables in the public schema. Laravel integration, table access controls, migrations, demo seed and CI remain implementation tasks. No real financial activity occurs in the demo.
