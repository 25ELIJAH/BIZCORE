# Development Roadmap and Progress

Last verified: 2026-11-21. Only items marked ✅ were actually run and checked (74 automated tests passing on MariaDB 11.8;
inventory page also exercised manually in a browser: login → create product with opening stock).

Assumptions A1–A8 in `PROJECT_ARCHITECTURE.md` were confirmed by the project owner.

## Phase 1 — Foundation ✅ (details in git history / tests)
Tenancy (fail-closed company scope, per-request company validation), RBAC (33 permissions, 9 roles, per-company roles),
authentication (throttling, password reset), activity log with redaction, layout with company switcher.

## Phase 2 — Core business modules ✅ (company-level admin UI still open, see Module A)

### Module A — Multi-company management ✅ (admin side)
- ✅ Filament (super administrators only): create / edit / view companies, activate / deactivate (no deletion), logo upload,
  currency, `allow_negative_stock` setting, audited
- ✅ Assign users to companies with a different role per company (Users and roles tab); change role; remove
- ✅ User management: create / edit / deactivate, password policy, hashed, never logged
- ⬜ Company-level administration for Company Administrators (own settings, assigning users in their own company) — permissions
  `company.settings.manage` / `company.users.manage` exist but no UI yet
- ⬜ Company-specific dashboard (Phase 4)

### Module B — Inventory ✅ (core)
- ✅ Products (SKU unique per company, prices, min stock, unit, active flag), categories, archive/restore (history kept)
- ✅ `StockService`: add / deduct / count-adjust under row locks, movement ledger (previous/new quantity, user, reason,
  reference), negative stock only if the company enables it, weighted-average cost and valuation, low-stock flag
- ✅ Livewire pages: Products (search, category / low-stock / archived filters, pagination, modals), Categories, Stock movements
  (product / type / date filters)
- ✅ Sale deducts stock (Sales) and goods receipt increases stock (Procurement)
- ⬜ Inventory valuation report (Phase 4)

### Module C — Sales and customers ✅ (core; verified by 113 tests + a sale completed in the browser)
- ✅ Customers: list/search/archive, create/edit, profile with outstanding balance, overdue count and transaction history
- ✅ `SaleService`: one transaction for sale + items + invoice + stock deduction (+ optional first payment); fixed or percent
  discount, tax rate (company default or per sale), due date (company payment terms), price/cost/name snapshots,
  walk-in customers, insufficient stock rolls everything back (invoice number not burned)
- ✅ Invoice numbers `INV-YYYY-00001`, per company per year, row-locked sequence
- ✅ `PaymentService`: partial payments, overpayment refused, idempotency key (double submit / duplicate-safe),
  company-configurable methods, void with reason (never deleted); `amount_paid`/status derived from non-voided payments
- ✅ Cancel sale: stock restored by a reversing movement; blocked while payments exist (void first); history kept
- ✅ Edit limited to due date + notes (`sales.edit`); items and amounts are immutable (correct by cancel + re-create)
- ✅ Sales list: filters (status, payment status incl. overdue, customer, date range, invoice/customer search), pagination
- ✅ Invoice PDF (view inline / download) via dompdf — validated as a PDF in tests; **layout not yet visually reviewed**
- ✅ Finance hook: customer payments create income entries (Module F)
- ✅ Customer receivables ageing report (Phase 4)

### Module E — Procurement ✅ (core; verified by 33 new tests + a purchase flow run in the browser)
- ✅ Suppliers: list/search/archive, create/edit (shared page with customers); profile with outstanding balance, open orders
  and purchase history
- ✅ `PurchaseOrderService`: draft (edit only while draft) -> submit -> approve / reject (back to draft with reason) ->
  cancel (reason; blocked once goods are received or active payments exist). Numbers `PO-YYYY-00001`. Supplier must be an
  active supplier of the current company; tax rate from company setting; money in bcmath
- ✅ `GoodsReceiptService`: the ONLY place a purchase order changes stock. Partial receipts, per-line quantity capped at the
  outstanding amount, items locked and scoped to the order, one transaction (failure changes nothing), status moves
  approved -> partially_received -> received. Stock via `StockService::add` (Purchase movement, weighted-average cost).
  Numbers `GRN-YYYY-00001`
- ✅ `SupplierPaymentService`: only against approved-or-later orders, amount capped at the balance, idempotency key,
  company payment methods, void with reason; `amount_paid` derived from non-voided payments
- ✅ Segregation of duties by permission: procurement officer creates/submits/receives; manager approves; accountant pays
- ✅ Livewire pages: purchase order list (status/supplier/date/search filters), create/edit form, detail page with
  workflow actions, receive-goods dialog, payments + void
- ✅ Finance hook: supplier payments create expense entries (Module F)
- ⬜ Purchase PDF / supplier statement report (Phase 4)

## Phase 3 — Finance and HR ✅ (verified by 56 new tests, 202 in total, plus a browser check)

### Module F — Finance and expenses ✅
- ✅ `FinanceService`: income / expense entries with categories (unique name per type per company; type always taken from the
  category), amount > 0, description required, edit, void with reason (never deleted)
- ✅ Payments feed finance without double counting: every customer payment becomes income and every supplier payment becomes an
  expense, inside the payment's own transaction (a failed sale/payment leaves no entry). Entries are keyed on their source
  payment (unique per company), voiding the payment voids its entry, and derived entries cannot be edited by hand
- ✅ System categories ("Sales revenue", "Supplier purchases") are created on demand and cannot be renamed
- ✅ `php artisan finance:sync-payments`: idempotent backfill for payments recorded before this module existed
- ✅ Pages: income/expense list (type, category, date range, search, show voided, totals for the range), record/edit/void,
  categories. `finance.view` / `finance.manage` / `finance.categories.manage` enforced
- ⬜ Finance reports and charts (Phase 4)

### Module D — Human resources ✅ (core)
- ✅ Departments, employees (number `EMP-YYYY-00001`, optional linked login, annual leave allocation), terminate (history kept,
  pending leave cancelled)
- ✅ Sensitive data (salary, national ID, bank account): hidden from serialisation, never logged, never sent to the browser
  or editable without `employees.view_sensitive`; editing without it leaves them untouched
- ✅ Attendance: one record per employee per day (correctable), no future dates, no dates before hire / after termination,
  whole-day sheet saved all-or-nothing
- ✅ Leave: working-day count (Mon-Fri), no overlap, annual allowance enforced including pending requests (A6), cannot span
  two years, approve / reject (reason required) / cancel before start, nobody can approve their own request,
  staff see and request only their own leave, HR can request for anyone
- ⬜ Payroll and payslips are NOT built (not in the proposal's module list); salary is stored for HR reference only
- ⬜ Public holidays are not excluded from leave days (only weekends)

## Phase 4 — Dashboard, reports, exports ✅ (verified by 29 new tests, 229 in total, plus a browser check)
- ✅ Dashboard (`/`): sales, finance, inventory, procurement and HR sections. Each section is computed and shown only if the
  user holds its permission (`sales.view`, `finance.view`, `inventory.view`, `purchase_orders.view`, `employees.view`), and the
  whole dashboard needs `dashboard.view`. Chart.js (bundled via Vite, no CDN) draws sales by month, top products and income vs
  expenses; charts carry text alternatives
- ✅ `ReportService`: one standard report shape feeds the screen, the PDF and the Excel export, so they cannot disagree.
  Reports: Sales, Product profitability, Inventory valuation, Customer receivables ageing, Supplier payables,
  Income and expenses, Leave balances. Date range where it makes sense; all money is decimal strings
- ✅ Report permissions: operational reports need `reports.view`, financial ones `reports.financial.view`, each plus the data
  module's own view permission. Unknown or unpermitted reports return 404; the index lists only what the role may open
- ✅ Export: PDF (dompdf) and Excel (maatwebsite/excel), needs `reports.export`, dates validated, every export written to the
  activity log. Excel cells that start with `= + - @` are neutralised against formula injection; money is written as numbers
- ✅ Isolation: reports, dashboard and exports contain only the current company's data (tests)
- ⬜ PDF/Excel layouts have been checked as valid files only; not reviewed visually
- ⬜ Charts are bar charts of the last six months; no custom periods yet

## Phase 5 — Integration QA ✅ (11 new tests, 240 in total, 985 assertions)
- ✅ `RouteSecurityTest`: walks every authenticated route. Guests are redirected to login; a member holding no permissions
  gets 403/404 on every business page (only the dashboard shell is open); a fully privileged user never gets a 5xx; the admin
  panel is closed to company users. New routes are covered automatically
- ✅ `CrossModuleIntegrationTest`: one purchase -> approve -> receive -> sell -> collect -> pay cycle; stock, documents, finance,
  dashboard and profit/loss all reconcile. Voiding payments reverses documents, finance and reports together; cancelling a sale
  restores stock. Invariants after a busy period: stock equals the ledger sum for every product, `amount_paid` equals the active
  payments on every sale and PO, finance equals active payments plus manual entries, invoice/PO numbers are unique, nothing is
  overpaid. The dashboard's query count does not grow with the number of records (no N+1)
- ✅ Defence in depth: `addProduct` / `removeLine` on the sale and purchase-order forms now authorize too
- Findings: no defects were found in the business logic. The only code change was the four authorize() calls above
- ⬜ Not done: MySQL 8 run; parallel-request concurrency test (row locks are covered by design and sequential tests only);
  visual review of PDF/Excel. Production settings go in the installation guide (Phase 6): `APP_ENV=production`,
  `APP_DEBUG=false`, secure session cookies, a real mail driver

## Phase 6 — Documentation and finalisation ✅
- ✅ Added `DATABASE_SCHEMA.md`, `MODULE_DOCUMENTATION.md`, `RBAC_PERMISSIONS.md`, `INSTALLATION_GUIDE.md`, `TESTING_GUIDE.md`;
  rewrote `README.md`; added a status note to `PROJECT_ARCHITECTURE.md` listing where the build differs from the plan
- ✅ `.env.example` now defaults to MySQL (`bizcore`) and the app name BizCore ERP, since SQLite is unsupported
- ✅ No `API_DOCUMENTATION.md`: the application exposes no API
- Honest findings recorded in the docs: `company.settings.manage`, `company.users.manage`, `activity_logs.view` are declared
  but have no screen yet; `tax_rate`, `payment_terms_days`, `payment_methods` have no editing screen

## Known gaps / notes
- Tests run on MariaDB 11.8; run them on MySQL 8 before production.
- `quantity` / `average_cost` on products are not mass-assignable; only `StockService` changes them.
- Livewire needs the company middleware registered as persistent (done in `AppServiceProvider`); a test guards this.

## Next step
Before go-live: run the suite on MySQL 8, load-test concurrent stock/payment writes, review PDF/Excel visually. Also still open: company-administrator settings UI (tax rate, payment terms, payment methods) and user assignment inside a company.
