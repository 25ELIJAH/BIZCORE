# BizCore ERP — Project Architecture

Source of truth: `BizCore_ERP_One_Page_Project_Proposal.pdf` (8 modules: multi-company, inventory, sales/customers,
HR, procurement, finance, dashboard/reports, access control) plus the engineering brief.

> **Status note (end of Phase 6).** Sections 2, 4 and 5 below were the *design plan*. The implementation differs in a few
> places, and the authoritative descriptions are now `DATABASE_SCHEMA.md`, `RBAC_PERMISSIONS.md` and
> `MODULE_DOCUMENTATION.md`. Differences: permissions are registered as Laravel Gates from `PermissionCatalog` (there is no
> `PermissionChecker` class); there are no policies or Form Requests (services validate, components authorize via Gates);
> Filament manages companies and users only (categories live in the Livewire app; roles are seeded data); income and
> expenses are one `finance_entries` table; corrections are voids, not reversal rows; there is no `leave_balances` table;
> Chart.js is installed and in use.

## 1. Verified stack (installed and resolving together)

| Layer | Choice | Version |
|---|---|---|
| Runtime | PHP | 8.3.9 |
| Framework | Laravel | 13.43 |
| Database | MySQL-compatible (MariaDB locally) | 11.8.9 |
| Admin panel | Filament | 5.11 (brings Livewire 4.x, Tailwind 4) |
| PDF | barryvdh/laravel-dompdf | ^3.1 |
| Excel | maatwebsite/excel | ^4.0 |
| Charts | Chart.js | 4.x via npm, bundled by Vite |

Local tooling lives outside the repo in `%USERPROFILE%\devtools` (composer.phar, MariaDB). Dev DB: `bizcore`;
test DB: `bizcore_test` (root, no password, **local dev only**).

## 2. Architecture

Modular monolith, Laravel conventions, domain grouping inside `app/`:

```
app/
  Models/                 Eloquent models (one folder per domain: Inventory, Sales, Hr, Procurement, Finance, Access)
  Models/Concerns/        BelongsToCompany (global scope + auto company_id), Auditable
  Support/Tenancy/        CurrentCompany (resolved per request), SwitchCompany action
  Services/               Business operations, each wrapped in DB transactions:
                          SaleService, PaymentService, StockService, PurchaseOrderService,
                          GoodsReceiptService, LeaveService, FinanceService, ReportService
  Http/Middleware/        EnsureCompanySelected, SetCurrentCompany
  Http/Requests/          Form Requests (validation)
  Policies/               One policy per model; all call PermissionChecker
  Livewire/               Page components per module (thin; delegate to services)
  Filament/               Platform administration: companies, users, roles, financial categories
  Exports/                Excel exports;   resources/views/pdf/ : invoice + report PDFs
database/migrations|factories|seeders
tests/Feature, tests/Unit
```

Rules: no business logic in Blade/Livewire views; services own transactions; policies own authorization;
money = `decimal(15,2)`, quantities = `decimal(15,3)`; every tenant table has `company_id` FK + index.

**UI split (assumption A1):** daily operations are a custom Livewire + Tailwind application (sidebar, company
switcher always visible). Filament is used for administration (companies, users, roles/permissions, categories).

## 3. Multi-company isolation

- Single database, shared schema, `company_id` on every tenant table (best fit for cross-company user switching).
- `company_user` pivot: `user_id, company_id, role_id` — one user, a different role per company.
- Active company is stored in the session **as an id only**; middleware re-validates it against `company_user`
  (and company `is_active`) on **every request** — a tampered session or request parameter cannot grant access.
- `BelongsToCompany` trait: global scope `where company_id = CurrentCompany::id()`, and sets `company_id` on create
  (never accepted from input). Route-model binding resolves through the scope, so another company's record ID gives 404.
- Cross-company references (e.g. a sale item pointing at another company's product) are rejected by validation
  rules scoped to the current company, and covered by tests.
- Super Administrator is a flag on `users`; it can see all companies but still acts inside one selected company.

## 4. Roles and permissions

Custom (not a package) to match the required tables `roles`, `permissions`, `role_permissions`.
- `permissions`: seeded, code-defined list of action slugs (e.g. `sales.view`, `sales.create`, `sales.cancel`,
  `inventory.adjust`, `leave.approve`, `purchase_orders.approve`, `reports.financial.view`, `company.settings.manage`).
- `roles`: the nine requested defaults seeded as editable data; `role_permissions` pivot.
- Check path: `PermissionChecker::can(user, permission)` = super-admin OR role-of-user-in-current-company has permission
  (cached per request). Policies, route middleware (`can:`), Livewire actions and navigation all use this one path.
- Sensitive HR data (salary, emergency contact) behind its own permission `employees.view_sensitive`.

## 5. Database entities

Tenant-scoped (carry `company_id`): departments, employees, attendance_records, leave_requests, leave_balances,
product_categories, products, stock_movements, customers, sales, sale_items, invoices, payments, suppliers,
purchase_orders, purchase_order_items, goods_receipts, goods_receipt_items, financial_categories, expenses,
income_records, activity_logs. Global: users, companies, company_user, roles, permissions, role_permissions.

Key relationships: Company 1–N everything above · Sale 1–N SaleItem, 1–1 Invoice, 1–N Payment ·
PurchaseOrder 1–N Item, 1–N GoodsReceipt 1–N GoodsReceiptItem · Product 1–N StockMovement (polymorphic reference to
Sale/GoodsReceipt/adjustment) · Expense/IncomeRecord polymorphic reference to originating Payment/Sale/PO ·
Employee 1–N Attendance/LeaveRequest, 1–N LeaveBalance. Unique constraints per company: `(company_id, sku)`,
`(company_id, invoice_number)`, `(company_id, employee_number)`, `(company_id, po_number)`.
Financial rows are never deleted: corrections are reversal entries (`reverses_id`) with audit log.

## 6. Key business rules

- Sale completion (one transaction): lock products → validate stock → create items → create invoice → decrement stock
  with `stock_movements` (previous/new quantity) → update customer balance. Negative stock only if company setting allows.
- Payments: idempotency key blocks duplicate submits; invoice status derived from paid total (unpaid / partial / paid / overdue);
  each payment creates exactly one income record linked by reference.
- Purchase orders: Draft → Pending approval → Approved → Partially received → Received, or Cancelled.
  Stock changes **only** in goods receipts; received qty cannot exceed ordered qty.
- Finance: "net operating balance" = income − expenses on a recorded basis; receivables/payables shown separately; not an accounting system.

## 7. Phases and dependencies

1. Foundation → 2. Core modules (company, inventory, customers, suppliers, sales, procurement; sales needs inventory,
procurement needs suppliers+inventory) → 3. Finance + HR (finance hooks into sales/procurement payments) →
4. Dashboard/reports (needs all data) → 5. Integration + QA → 6. Finalisation/docs. See `DEVELOPMENT_ROADMAP.md`.

## 8. Assumptions needing confirmation

- A1 UI split above (Livewire app + Filament admin) vs Filament for everything.
- A2 Single currency per company; no multi-currency conversion.
- A3 Tax is a configurable rate per company (and overridable per sale); no tax-jurisdiction engine.
- A4 Stock valuation = weighted average cost.
- A5 Supplier payments are recorded against purchase orders (no separate bills/AP ledger).
- A6 Leave balances are annual allocations per leave type; no accrual engine.
- A7 Overdue = past invoice due date with outstanding balance; default terms configurable per company.
- A8 Password reset needs a mail driver; dev uses the log driver.

## 9. Technical risks

- Laravel 13 / Filament 5 / Livewire 4 are very new; documentation and community answers are thinner.
- Tenant isolation must not be bypassed by raw queries, `withoutGlobalScopes`, or queue jobs (no request context) — covered by tests.
- Filament panels need their own tenant-scoping decisions; Filament must use the same scope.
- Concurrency on stock (needs row locks); MariaDB vs MySQL differences (verify CI/production on real MySQL 8).
- Scope: this is a multi-week product; progress is tracked phase by phase in `DEVELOPMENT_ROADMAP.md`.
