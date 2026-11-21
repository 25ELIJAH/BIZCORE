# BizCore ERP — Roles and Permissions

## How access is decided

1. **Authentication** — Laravel session login. Inactive users (`users.is_active = false`) are logged out on their next request.
2. **Company context** — the session stores only a company *id*. `ResolveCurrentCompany` re-validates it against the
   user's memberships (`company_user`) and the company's `is_active` flag on **every** request. A forged id is ignored and
   the user falls back to a company they really belong to (or to the "no company" page).
3. **Permission** — a user's role is *per company* (`company_user.role_id`), so one person can be a Manager in one company
   and an Accountant in another. Every permission slug in `App\Support\Access\PermissionCatalog` is registered as a Laravel
   Gate that checks the user's role **in the current company**. Routes use `can:` middleware, Livewire actions call
   `authorize()`, Blade uses `@can`, and services re-check where a rule depends on the actor.
4. **Row access** — `CompanyScope` limits every tenant query to the current company, so even a permitted user receives a
   404 for another company's record id.

**Super administrator** is a flag on the user (`is_super_admin`), not a role. It passes every permission check, may enter
any active company, and is the only account that can open the Filament admin panel (`/admin`, companies and users).
Deactivated users pass nothing, super administrators included.

Roles are data, not code: the defaults below are seeded by `AccessSeeder` and can be edited in the database. There is no
roles screen yet (see *Known gaps*).

## Permission catalogue

| Group | Slug | Meaning |
|---|---|---|
| Company | `dashboard.view` | See the dashboard (sections inside are filtered by the module permissions below) |
| | `company.settings.manage` | *Reserved* — company settings are currently edited by the super administrator in `/admin` |
| | `company.users.manage` | *Reserved* — no in-company user management screen yet |
| | `activity_logs.view` | *Reserved* — activity is recorded, but there is no log viewer screen yet |
| Inventory | `inventory.view` | See products, categories, stock movements |
| | `inventory.manage` | Create/edit/deactivate products and categories |
| | `inventory.adjust` | Adjust stock (reason required) |
| Sales | `customers.view` / `customers.manage` | See / create-edit customers |
| | `sales.view` | See sales and invoices |
| | `sales.create` | Create a sale |
| | `sales.edit` | Edit due date and notes of a sale |
| | `sales.cancel` | Cancel a sale (payments must be voided first; stock is returned) |
| | `payments.record` | Record and void customer payments |
| HR | `employees.view` / `employees.manage` | See / create-edit employees and departments |
| | `employees.view_sensitive` | See and edit national ID, salary, bank account |
| | `attendance.manage` | Record and edit attendance |
| | `leave.request` | Submit own leave requests (and see own leave) |
| | `leave.approve` | Approve or reject others' leave; HR can also request for anyone |
| Procurement | `suppliers.view` / `suppliers.manage` | See / create-edit suppliers |
| | `purchase_orders.view` | See purchase orders |
| | `purchase_orders.manage` | Create and edit draft orders, submit, cancel |
| | `purchase_orders.approve` | Approve or reject orders pending approval |
| | `goods.receive` | Receive goods against an approved order (this is what increases stock) |
| | `supplier_payments.record` | Record and void supplier payments |
| Finance | `finance.view` | See income and expense entries |
| | `finance.manage` | Record and void manual entries |
| | `finance.categories.manage` | Create/edit finance categories |
| | `reports.view` | Operational reports (sales, inventory, leave balances…) |
| | `reports.financial.view` | Financial reports (profit, receivables, payables…) |
| | `reports.export` | Download PDF / Excel (every export is logged) |

A report additionally needs the view permission of the module whose data it shows (for example the inventory report
needs `inventory.view`). Unpermitted reports return 404, not 403, so their existence is not revealed.

## Default roles

`✔` = granted. Super Administrator and Company Administrator hold every permission above.

| Permission | Manager | Accountant | Inventory Officer | HR Officer | Sales Officer | Procurement Officer | Employee |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| dashboard.view | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| activity_logs.view | ✔ | | | | | | |
| inventory.view | ✔ | | ✔ | | ✔ | ✔ | |
| inventory.manage | | | ✔ | | | | |
| inventory.adjust | ✔ | | ✔ | | | | |
| customers.view | ✔ | ✔ | | | ✔ | | |
| customers.manage | | | | | ✔ | | |
| sales.view | ✔ | ✔ | | | ✔ | | |
| sales.create | ✔ | | | | ✔ | | |
| sales.edit | ✔ | | | | ✔ | | |
| sales.cancel | ✔ | | | | | | |
| payments.record | | ✔ | | | ✔ | | |
| employees.view | ✔ | | | ✔ | | | |
| employees.manage | | | | ✔ | | | |
| employees.view_sensitive | | | | ✔ | | | |
| attendance.manage | | | | ✔ | | | |
| leave.request | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| leave.approve | ✔ | | | ✔ | | | |
| suppliers.view | ✔ | ✔ | | | | ✔ | |
| suppliers.manage | | | | | | ✔ | |
| purchase_orders.view | ✔ | ✔ | ✔ | | | ✔ | |
| purchase_orders.manage | | | | | | ✔ | |
| purchase_orders.approve | ✔ | | | | | | |
| goods.receive | | | ✔ | | | ✔ | |
| supplier_payments.record | | ✔ | | | | | |
| finance.view | ✔ | ✔ | | | | | |
| finance.manage | | ✔ | | | | | |
| finance.categories.manage | | ✔ | | | | | |
| reports.view | ✔ | ✔ | ✔ | ✔ | | | |
| reports.financial.view | ✔ | ✔ | | | | | |
| reports.export | ✔ | ✔ | | | | | |

(Source: `App\Support\Access\DefaultRoles`. If this table ever differs from that file, the file is correct.)

## Rules enforced beyond plain permissions

- **Separation of duties:** nobody can approve their own leave request.
- **Self-service leave:** staff with only `leave.request` see and request just their own leave (their login must be linked to
  an employee record).
- **Sensitive HR data** is hidden from serialisation and only loaded for `employees.view_sensitive`.
- **Idempotent payments:** a repeated submit with the same key is a no-op, not a second payment.
- **Void / cancel need a reason**, and are written to the activity log with the actor and IP address.
- **Activity log redaction:** keys such as passwords, tokens, salary and bank details are redacted before logging.
- **Admin panel:** `canAccessPanel` is true only for active super administrators.

## Verification

Permission behaviour is covered by `RouteSecurityTest` (every route, guest / no-permission / privileged),
`PermissionEnforcementTest`, `CompanyIsolationTest`, and the per-module page tests that assert each role sees or is refused
what the table above says. Run `php artisan test`.

## Known gaps

- `company.settings.manage`, `company.users.manage`, `activity_logs.view` are defined and assigned but have no screen to guard yet.
- No UI to edit roles/permissions or to assign users to roles inside a company; the super administrator assigns company roles from each company's page in `/admin`.
- Tax rate, payment terms and payment methods are read from `companies.settings` but have no editing screen yet.
