# BizCore ERP — Module Documentation

How each of the eight modules behaves, which service owns its rules, and which screens expose it. Permissions are listed
in `RBAC_PERMISSIONS.md`, tables in `DATABASE_SCHEMA.md`.

**Shared mechanics.** Business rules live in `app/Services/*`; Livewire components under `app/Livewire/*` are thin and call
services. Every critical operation runs in a database transaction with row locks where concurrent writes could conflict.
Money is `decimal` and handled with bcmath (`App\Support\Money`, rounding half up). A rule violation raises
`BusinessRuleException`, which screens show as a form message. Significant actions are written to the activity log.

---

## 1. Multi-company management

- **What:** several companies in one installation; users belong to one or more, with a role per company. Switch company from
  the header; the choice is stored as an id in the session and re-validated each request.
- **Isolation:** `BelongsToCompany` (global scope + automatic `company_id`), `CurrentCompany` singleton (`runAs()` for
  console/queue work), `ResolveCurrentCompany` middleware (also registered as Livewire persistent middleware, so Livewire
  update requests are validated too). Another company's record id returns 404.
- **Administration:** the Filament panel at `/admin` (super administrators only) manages companies (name, registration number,
  address, currency, logo, active flag, *allow selling beyond stock*) and users (active flag, super-admin flag, company roles).
- **Company settings** read from `companies.settings`: `tax_rate`, `payment_terms_days`, `payment_methods`,
  `allow_negative_stock`. Only the last has an editing screen today.

## 2. Inventory

- **Screens:** Products (`/inventory/products`), Categories, Stock movements.
- **Rules (`StockService`, `ProductService`):**
  - Quantity changes only through `StockService`, under a row lock, and every change appends a `stock_movements` row with
    previous/new quantity. The ledger is never edited or deleted.
  - Stock cannot go below zero unless the company allows it.
  - Manual adjustments require a reason.
  - Inbound movements with a unit cost update the **weighted-average cost** (assumption A4).
  - Products with history are archived, not deleted. SKU is unique per company.
  - Low-stock = `quantity <= min_stock`.

## 3. Sales and customers

- **Screens:** Customers (list, detail with balance and history), Sales (list, create, detail), printable PDF invoice.
- **Creating a sale (`SaleService::create`, one transaction):** locks the products → validates stock → snapshots name, SKU,
  price and cost on each line → computes subtotal, discount (fixed or percent), tax (company rate, overridable) and total
  with decimals → deducts stock with `sale` movements → creates the invoice (`INV-YYYY-00001`) → optionally records a
  first payment. Walk-in customers are supported (no customer).
- **Payments (`PaymentService`):** partial payments allowed, cannot exceed the balance, idempotency key prevents duplicates.
  `amount_paid` and `payment_status` (unpaid / partial / paid) are recomputed from active payments. A payment can be
  voided with a reason, which recomputes the sale and voids its finance entry.
- **Cancel:** needs a reason; only when no active payments remain (void them first); returns stock with `sale_reversal`.
- **Overdue** (A7): completed, not fully paid, past `due_date`.
- Invoices are immutable apart from due date and notes (`sales.edit`).

## 4. Human resources

- **Screens:** Employees and departments, Attendance, Leave.
- **Employees (`EmployeeService`):** auto number `EMP-YYYY-00001`; optional link to a login user (one employee per user per
  company; the user must belong to the company). Salary, national ID and bank account need `employees.view_sensitive`
  to be seen or edited. Termination records a date and reason; terminated staff are kept for history. A department that
  still has employees cannot be deleted.
- **Attendance (`AttendanceService`):** one record per employee per day (present, absent, late, half day, on leave);
  a whole-day sheet is saved all-or-nothing; check-out cannot precede check-in.
- **Leave (`LeaveService`):** working days = Monday–Friday (public holidays are **not** excluded); no overlapping
  requests; annual leave limited to the yearly allowance **including pending requests** (A6); a request cannot span two
  years; approve / reject (reason required) / cancel before start; nobody approves their own request.
- **Not built:** payroll and payslips (not in the proposal's module list).

## 5. Procurement and suppliers

- **Screens:** Suppliers (list, detail with outstanding balance), Purchase orders (list, form, detail).
- **Lifecycle (`PurchaseOrderService`):** `draft → pending_approval → approved → partially_received → received`;
  rejection returns to `draft` with a reason; cancellation needs a reason and is blocked once goods or payments exist.
  Only drafts can be edited. Totals are recomputed on the server; PO numbers are `PO-YYYY-00001`.
- **Receiving (`GoodsReceiptService`):** the only way PO stock increases. One transaction: lock the PO → check each line
  does not exceed the quantity still due → create the goods receipt (`GRN-YYYY-00001`) → add stock with the line cost,
  updating weighted-average cost → advance the PO to partially/fully received.
- **Supplier payments (`SupplierPaymentService`, A5):** recorded against approved/received POs, cannot exceed the PO balance,
  idempotent, voidable with a reason. Creates/voids the matching finance expense.

## 6. Finance and expenses

- **Screens:** Income & expenses (`/finance`), Categories.
- **Model:** one ledger (`finance_entries`) of income and expense. **Derived** entries are created automatically from
  customer payments (income) and supplier payments (expense); the unique `(company, source_type, source_id)` key makes
  double counting impossible, and they can only be changed by voiding the payment. **Manual** entries (rent, utilities…)
  are recorded by users with `finance.manage` and can be edited or voided with a reason.
- **Categories:** per company, typed income/expense; system categories ("Customer payments", "Supplier payments") are
  created by the app and are not editable.
- **Summary (`FinanceService::summary`):** income, expense, net and per-category totals for a date range, from non-voided
  entries only. This is a recorded-cash view, not a double-entry ledger.
- **Backfill:** `php artisan finance:sync-payments` creates entries for payments that pre-date the module; safe to rerun.

## 7. Dashboard and reports

- **Dashboard (`/`):** sections for sales, finance, inventory, procurement and HR; each is calculated and shown only if
  the user holds that module's view permission (and `dashboard.view` for the page). Charts are Chart.js (bundled by Vite,
  no CDN): monthly sales, top products, income vs expenses, with text alternatives.
- **Reports (`/reports`, `ReportService`):** Sales; Product profitability; Inventory valuation; Customer receivables ageing;
  Supplier payables; Income and expenses; Leave balances. Every report builds one standard structure (title, subtitle,
  columns, rows, totals) used by the screen, the PDF and the Excel file, so the three cannot disagree.
- **Export:** PDF (dompdf) and Excel (maatwebsite/excel); needs `reports.export` plus the report's own permissions, validates the
  date range, is audited, writes money as numbers, and neutralises cells starting with `= + - @` against formula injection.

## 8. Security and access control

- Authentication with session login, password reset by e-mail (dev uses the `log` mail driver — A8), deactivation support.
- RBAC as described in `RBAC_PERMISSIONS.md`; company isolation as in module 1.
- **Activity log:** login, company switch, document create/cancel/void, approvals, exports, and so on, with user, company, IP
  and JSON properties; sensitive keys are redacted.
- Mass-assignment protection: money models are `$guarded = ['*']` and written with `forceFill` in services only.
- Output is escaped by Blade; CSRF is on for all state-changing routes; Excel exports are formula-injection safe.

---

## Known limitations

- No in-company screens yet for company settings (tax rate, payment terms, payment methods), user/role assignment, a role
  editor, or an activity-log viewer.
- Single currency per company (A2); no multi-currency conversion. Tax is one configurable rate, not a jurisdiction engine (A3).
- Finance is a recorded-income/expense view, not full double-entry accounting.
- No payroll; leave ignores public holidays.
- No public API, so there is no `API_DOCUMENTATION.md`.
