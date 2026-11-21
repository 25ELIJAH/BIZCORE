# BizCore ERP

Multi-company business management system: inventory, sales and customers, procurement and suppliers, finance, HR,
dashboards and reports, with role-based access and strict per-company data isolation.

All eight proposal modules are implemented and tested (see *Status*). Known gaps are listed honestly in
`MODULE_DOCUMENTATION.md` and `DEVELOPMENT_ROADMAP.md`.

## Stack
PHP 8.3 · Laravel 13 · MySQL 8 / MariaDB 11 · Livewire 4 + Tailwind CSS 4 (daily operations) · Filament 5 (super-admin
administration) · Chart.js · dompdf · maatwebsite/excel

## Quick start
```bash
composer install && npm install
cp .env.example .env            # set DB_* (MySQL/MariaDB; SQLite is not supported)
php artisan key:generate
php artisan migrate --seed      # permissions + roles; demo data only when APP_ENV=local
php artisan storage:link
npm run build
php artisan serve
```
Full instructions, production checklist and troubleshooting: `INSTALLATION_GUIDE.md`.

Demo accounts (local only, password `password`): `super@bizcore.test` (super admin, `/admin`),
`admin@bizcore.test` (Company Administrator, Acme), `multi@bizcore.test` (Manager in Acme, Accountant in Globex).

## Modules
Multi-company · Inventory (append-only stock ledger, weighted-average cost) · Sales and customers (invoices, partial
payments, PDF) · Procurement (PO approval, goods receipts, supplier payments) · Finance (income/expense derived from
payments plus manual entries) · HR (employees, attendance, leave) · Dashboard and reports (Chart.js, PDF, Excel) ·
Security (RBAC per company, activity log).

## Design principles
- Every tenant table has `company_id`; a fail-closed global scope and per-request company validation isolate tenants.
- Services own business rules and DB transactions; Livewire components stay thin.
- Money is decimal (bcmath); financial records are voided with a reason, never deleted.
- No hardcoded business data; permissions are checked on routes, actions and in services.

## Status
Phases 1–6 complete. 240 automated tests pass on MariaDB 11.8. **Not yet done:** a run on MySQL 8, parallel-request
load testing, visual review of PDF/Excel layouts, in-company screens for settings/users/roles, payroll.

## Documentation
| File | Contents |
|---|---|
| `PROJECT_ARCHITECTURE.md` | Design, isolation model, assumptions A1–A8, risks |
| `DATABASE_SCHEMA.md` | Tables, columns, constraints, relationships |
| `MODULE_DOCUMENTATION.md` | Behaviour and rules of each module, limitations |
| `RBAC_PERMISSIONS.md` | Access model, permission catalogue, default role matrix |
| `INSTALLATION_GUIDE.md` | Local and production setup |
| `TESTING_GUIDE.md` | Running tests, coverage, conventions, gaps |
| `DEVELOPMENT_ROADMAP.md` | Phase-by-phase progress and QA findings |

There is no public API, so no API documentation exists.
