# BizCore ERP — Testing Guide

Status when this was written: **240 tests, 985 assertions, all passing** on MariaDB 11.8. Not yet run on MySQL 8.

## Running the tests

Tests use a real MySQL-compatible database (row locks and constraints matter), configured in `phpunit.xml` as the
`bizcore_test` database on `127.0.0.1` with the credentials from your environment. Create it once:

```sql
CREATE DATABASE bizcore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan test                                   # everything (about 2 minutes)
php artisan test tests/Feature/SalesServiceTest.php
php artisan test --filter=test_a_full_business_cycle
```

Each test class uses `RefreshDatabase`, so the schema is rebuilt and tests never share data. Mail is captured (`array`
driver), sessions/cache are in memory, and bcrypt rounds are lowered for speed. **Never point `bizcore_test` at your real
database:** it is wiped.

Make sure the database server is running first; a "connection refused" error means it is not.

## What the suites cover

| Suite | Focus |
|---|---|
| `AuthenticationTest` | Login, throttling/lockout, logout, password reset, deactivated users |
| `CompanyIsolationTest` | A user in company A cannot read or bind company B's records; scope is fail-closed |
| `CompanySwitchingTest` | Session company is validated; forged ids rejected |
| `PermissionEnforcementTest` | Gates per role, per company; one user with different roles in two companies |
| `AdministrationTest` | Filament panel is super-admin only; company/user management |
| `StockServiceTest` | Ledger, weighted-average cost, negative-stock rule, locking behaviour, adjustments need a reason |
| `InventoryPagesTest` | Product/category/movement screens and their permissions |
| `SalesServiceTest`, `SalesPagesTest` | Sale totals, discounts, tax, stock deduction, invoices, partial payments, idempotency, void/cancel rules, overdue |
| `ProcurementServiceTest`, `ProcurementPagesTest` | PO lifecycle, approval, receiving, over-receipt refused, supplier payments, supplier isolation |
| `FinanceServiceTest`, `FinancePagesTest` | Derived entries from payments, no double counting, voids, manual entries, categories, summaries |
| `HrServiceTest`, `HrPagesTest` | Employees, sensitive fields, attendance, leave balances/overlap/approval rules |
| `ReportServiceTest`, `ReportPagesTest` | Report figures, dashboard sections per permission, PDF/Excel export, formula-injection safety, isolation |
| `RouteSecurityTest` | Every authenticated route: guests redirected, no-permission users refused, no 5xx for privileged users |
| `CrossModuleIntegrationTest` | Purchase → receive → sell → collect → pay, reversal of voids/cancellations, data-integrity invariants, dashboard query count |

### Invariants asserted in `CrossModuleIntegrationTest`

1. `products.quantity` equals the sum of the product's `stock_movements`.
2. `sales.amount_paid` and `purchase_orders.amount_paid` equal the sum of their non-voided payments.
3. Finance income/expense equals active payments plus manual entries.
4. Invoice and PO numbers are unique; no document is overpaid.
5. Dashboard query count does not grow with data volume (no N+1).

## Writing new tests — conventions and pitfalls

- Use `Tests\Concerns\CreatesTenants`: `userWithRole('manager', $company)` seeds roles and attaches the user.
- `Livewire::test()` bypasses the HTTP middleware, so set the company yourself:
  `app(CurrentCompany::class)->set($company)`. For real middleware behaviour (404 for foreign records) use `$this->get()`.
- Pass **models**, not ids, to typed Livewire properties in tests.
- After a 403 a chained `Livewire::test(...)` call is dead ("Invalid Livewire snapshot"); start a new `Livewire::test` for the next assertion.
- Money models are guarded; create them through services or factories, not `create([...amounts])`.
- To work inside another company use `app(CurrentCompany::class)->runAs($company, fn () => ...)`.
- Excel: `Excel::fake(); Excel::matchByRegex();` then assert the download by regex on the filename.
- Assert behaviour, not placeholders: a test with no meaningful assertion is worse than none.

## Not covered yet (be aware before production)

- **MySQL 8** (only MariaDB has been used). Run the full suite there.
- **True parallel concurrency** (two requests selling the last unit at once). Row locks are in the code and reasoned about,
  tests are sequential. Load-test before heavy use.
- **Visual review** of PDF and Excel layouts: tests check they are valid, correct files and contain the right numbers.
- Browser end-to-end automation: key flows were checked manually in a real browser during development, not by an automated suite.
- No static analysis (PHPStan/Larastan) is configured. Code style: `vendor/bin/pint`.
