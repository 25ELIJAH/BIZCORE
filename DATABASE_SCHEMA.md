# BizCore ERP — Database Schema

Generated from the migrations in `database/migrations/` (the migrations are the source of truth; if this file and a
migration disagree, the migration wins). Engine: MySQL 8 / MariaDB 11, InnoDB, `utf8mb4`.

## Conventions

- **Tenancy:** every business table carries `company_id` (FK → `companies`, cascade on delete) and is indexed with it.
  The `BelongsToCompany` trait adds a fail-closed global scope and fills `company_id` itself; it is never read from input.
- **Money:** `decimal(15,2)`. **Quantities:** `decimal(15,3)`. **Average cost / unit cost on stock rows:** `decimal(15,4)`.
  Percentages: `decimal(5,2)`. No floats anywhere; arithmetic uses bcmath through `App\Support\Money`.
- **Immutability:** money tables are never deleted from the application. Mistakes are corrected by *voiding* with a
  reason (`voided_at`, `voided_by`, `void_reason`) or by cancelling a document (`cancelled_at`, `cancel_reason`).
- **Snapshots:** documents copy product name, SKU and unit cost at the time of the transaction, so later edits to a
  product do not rewrite history.
- **Idempotency:** payments carry an optional `idempotency_key`, unique per company, so a double-submit cannot book twice.
- **Delete rules:** references to people (`user_id`, `approved_by`, `voided_by`…) are `nullOnDelete`; references that would
  orphan history (products on documents, suppliers on POs, categories on entries) are `restrictOnDelete`.
- Status columns are strings validated by the application (constants on the models), not DB enums.

## Global tables (no `company_id`)

| Table | Purpose | Notable columns / constraints |
|---|---|---|
| `users` | Login accounts | `email` unique; `is_super_admin`, `is_active`, `last_login_at` |
| `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Laravel framework tables | sessions are stored in the database |
| `companies` | Tenants | `name`, `registration_number` unique, `currency` char(3) default `USD`, `is_active`, `settings` JSON, `logo_path` |
| `roles` | Role definitions (shared by all companies) | `slug` unique, `is_system` |
| `permissions` | Permission slugs, seeded from `PermissionCatalog` | `slug` unique, `group` |
| `role_permissions` | Role ⇄ permission | FK both, cascade |
| `company_user` | **Membership: a user's role in a company** | unique `(company_id, user_id)`; `role_id` restrict |
| `activity_logs` | Audit trail | `company_id` nullable, `user_id`, `action`, `properties` JSON, `ip_address`; append-only |

`companies.settings` keys in use: `tax_rate`, `payment_terms_days`, `payment_methods` (slug → label map),
`allow_negative_stock`. Defaults come from `config/bizcore.php`.

## Inventory

| Table | Columns of note | Constraints / indexes |
|---|---|---|
| `product_categories` | `name`, `description` | unique `(company_id, name)` |
| `products` | `sku`, `name`, `unit`, `purchase_price`, `selling_price`, `average_cost` (4 dp), `quantity` (3 dp), `min_stock`, `is_active`, `product_category_id` (null on delete) | unique `(company_id, sku)`; index `(company_id, name)`, `(company_id, product_category_id)` |
| `stock_movements` | **Append-only ledger.** `type`, signed `quantity`, `previous_quantity`, `new_quantity`, `unit_cost`, `reason`, `user_id`, `created_at` only | index `(company_id, product_id, created_at)`, `(company_id, type)` |

Movement `type` values: `opening`, `purchase`, `sale`, `sale_reversal`, `adjustment_in`, `adjustment_out`, `addition`,
`deduction`. `products.quantity` and `average_cost` are changed only by `StockService`, under a row lock.
Invariant (tested): `products.quantity` = sum of `stock_movements.quantity` for the product.

## Customers, suppliers, sales

| Table | Columns of note | Constraints / indexes |
|---|---|---|
| `customers`, `suppliers` | `name`, `contact_person`, `email`, `phone`, `address`, `tax_number`, `notes`, `is_active` | index `(company_id, name)`, `(company_id, email)` |
| `document_sequences` | `type`, `year`, `last_number` — source of INV-/PO-/GRN-/EMP-YYYY-00001 numbers | unique `(company_id, type, year)`; row-locked when incrementing |
| `sales` | `customer_id` (null = walk-in), `sale_date`, `due_date`, `status` (completed/cancelled), `payment_status` (unpaid/partial/paid), `subtotal`, `discount_type`, `discount_value`, `discount_amount`, `tax_rate`, `tax_amount`, `total`, `amount_paid`, `cancelled_at`, `cancel_reason` | indexes on `(company_id, sale_date)`, `(…, status, payment_status)`, `(…, customer_id)`, `(…, due_date)` |
| `sale_items` | snapshot `product_name`, `sku`; `quantity`, `unit_price`, `unit_cost`, `line_total` | index `(company_id, product_id)` |
| `invoices` | `invoice_number`, `issue_date`, `due_date` | unique `sale_id`; unique `(company_id, invoice_number)` |
| `payments` | customer payments: `amount`, `method`, `paid_at`, `reference`, `idempotency_key`, void columns | unique `(company_id, idempotency_key)` |

`sales.amount_paid` is derived: the sum of that sale's non-voided `payments` (tested).

## Procurement

| Table | Columns of note | Constraints / indexes |
|---|---|---|
| `purchase_orders` | `supplier_id`, `po_number`, `status` (`draft`, `pending_approval`, `approved`, `partially_received`, `received`, `cancelled`; a rejection returns the order to `draft` with `rejection_reason`), `order_date`, `expected_date`, `subtotal`, `tax_rate`, `tax_amount`, `total`, `amount_paid`, `approved_by`, `submitted_at`, `approved_at`, `rejection_reason`, `cancelled_at`, `cancel_reason` | unique `(company_id, po_number)`; indexes on status, supplier, order date |
| `purchase_order_items` | snapshot `product_name`, `sku`; `quantity_ordered`, `quantity_received`, `unit_cost`, `line_total` | |
| `goods_receipts` | `purchase_order_id`, `receipt_number`, `received_date`, `notes` | unique `(company_id, receipt_number)` |
| `goods_receipt_items` | `purchase_order_item_id`, `product_id`, `quantity`, `unit_cost` | |
| `supplier_payments` | like `payments`, against a PO (`purchase_order_id`, `supplier_id`) | unique `(company_id, idempotency_key)` |

Stock increases only when goods are received (one transaction: receipt + items + `stock_movements` + weighted-average
cost). `purchase_orders.amount_paid` is the sum of non-voided `supplier_payments`.

## Finance

| Table | Columns of note | Constraints |
|---|---|---|
| `finance_categories` | `type` (income/expense), `name`, `is_system` (app-created, not editable), `is_active` | unique `(company_id, type, name)` |
| `finance_entries` | `type`, `amount`, `entry_date`, `description`, `reference`, `finance_category_id`, `source_type` (`payment`/`supplier_payment`/null for manual), `source_id`, void columns | **unique `(company_id, source_type, source_id)`** — a payment can produce at most one entry, so nothing is double counted |

Entries with a `source_*` are derived and cannot be edited; voiding the payment voids the entry. Manual entries have
`source_type` null. The command `php artisan finance:sync-payments` backfills entries for payments that pre-date the
finance module (idempotent).

## Human resources

| Table | Columns of note | Constraints |
|---|---|---|
| `departments` | `name` | unique `(company_id, name)` |
| `employees` | `employee_number`, names, `email`, `phone`, `job_title`, `hire_date`, `status` (active/terminated), `termination_date`, `annual_leave_days` (default 21), **sensitive:** `national_id`, `salary`, `bank_account`; optional `user_id` login link | unique `(company_id, employee_number)`, unique `(company_id, user_id)` |
| `attendance_records` | `date`, `status` (present, absent, late, half_day, on_leave), `check_in`, `check_out`, `notes`, `recorded_by` | unique `(employee_id, date)` |
| `leave_requests` | `type` (annual, sick, unpaid, other), `start_date`, `end_date`, `days` (working days), `status` (pending, approved, rejected, cancelled), `requested_by`, `decided_by`, `decision_note` | index `(employee_id, start_date, end_date)` |

The sensitive employee columns are `hidden` on the model and only exposed to holders of `employees.view_sensitive`.

## Relationships (summary)

```
Company 1─N  every tenant table                 Company N─N User   (via company_user, carrying role_id)
Role    N─N  Permission (role_permissions)
Customer 1─N Sale 1─N SaleItem                  Sale 1─1 Invoice        Sale 1─N Payment
Supplier 1─N PurchaseOrder 1─N PurchaseOrderItem
PurchaseOrder 1─N GoodsReceipt 1─N GoodsReceiptItem        PurchaseOrder 1─N SupplierPayment
Product  1─N StockMovement                      Product 1─N SaleItem / PurchaseOrderItem
FinanceCategory 1─N FinanceEntry                FinanceEntry ─ (source_type, source_id) → Payment | SupplierPayment
Department 1─N Employee 1─N AttendanceRecord / LeaveRequest      Employee 0..1─1 User
```

## Deviations from the original design sketch

`PROJECT_ARCHITECTURE.md` §5 was an early plan. The built schema differs in these ways: income and expenses are one
table (`finance_entries`) with a `type`, instead of `expenses` + `income_records`; corrections use void columns instead
of reversal rows; there is no `leave_balances` table (balances are computed from `employees.annual_leave_days` and the
approved/pending requests); customer balances are computed from sales, not stored.
