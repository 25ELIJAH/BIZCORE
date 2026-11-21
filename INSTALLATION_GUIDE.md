# BizCore ERP — Installation Guide

## 1. Requirements

| Item | Version |
|---|---|
| PHP | 8.3+ with extensions `pdo_mysql`, `mbstring`, `openssl`, `gd`, `zip`, `intl`, `fileinfo`, `curl`, `bcmath` |
| Composer | 2.x |
| Node.js | 20+ (build step only) |
| Database | MySQL 8 or MariaDB 11 (InnoDB). **SQLite is not supported:** stock and payment safety rely on row locks |

Development of this project was done on MariaDB 11.8. The automated suite has **not yet been run on MySQL 8** — run it
there before going live (see `TESTING_GUIDE.md`).

## 2. Local installation

```bash
composer install
npm install
cp .env.example .env            # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

Create an empty database (default name `bizcore`) and set `DB_USERNAME` / `DB_PASSWORD` in `.env`, then:

```bash
php artisan migrate --seed      # permissions + default roles; demo companies/users only when APP_ENV=local
php artisan storage:link        # company logos are stored on the public disk
npm run build                   # or `npm run dev` while developing
php artisan serve
```

Open http://localhost:8000.

### Demo accounts (local only, password `password`)

| Email | Access |
|---|---|
| `super@bizcore.test` | Super administrator (all companies, `/admin`) |
| `admin@bizcore.test` | Company Administrator in *Acme Trading Ltd* |
| `multi@bizcore.test` | Manager in Acme, Accountant in *Globex Supplies* |

`DevelopmentSeeder` refuses to run unless `APP_ENV=local`, so these accounts never appear in production.

## 3. Production deployment

1. **Environment** (`.env`):
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain
   DB_CONNECTION=mysql   # plus host, database, a dedicated non-root user with a strong password
   SESSION_SECURE_COOKIE=true
   SESSION_DOMAIN=your-domain
   MAIL_MAILER=smtp      # a real mailer: the default `log` driver means password-reset mails are never delivered
   MAIL_FROM_ADDRESS=...
   ```
   Never commit `.env`. Rotate any credential that has ever appeared in a repository or chat.
2. **Install:** `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
3. **Database:** `php artisan migrate --force` then `php artisan db:seed --class=AccessSeeder --force`
   (idempotent; seeds permissions and roles without touching edits you made to existing roles).
4. **Caches:** `php artisan config:cache && php artisan route:cache && php artisan view:cache`; `php artisan storage:link`.
5. **Web server:** point the document root at `public/`; serve over HTTPS; make `storage/` and `bootstrap/cache/` writable by
   the PHP user.
6. **First super administrator** (there is no public registration):
   ```bash
   php artisan tinker
   >>> App\Models\User::create(['name' => 'Your Name', 'email' => 'you@example.com', 'password' => 'choose-a-long-password', 'is_super_admin' => true]);
   ```
   (The password is hashed automatically.) Sign in, open `/admin`, create the first company, then create users and assign each a
   role from the company's page.
7. **Backups:** schedule regular database dumps and back up `storage/app/public` (logos). Test a restore.
8. **Existing payments before the finance module?** Run `php artisan finance:sync-payments` once; it is safe to repeat.

The application needs no queue worker or scheduler today (`QUEUE_CONNECTION=database` is configured but nothing is queued).

## 4. Company settings

A company's currency and *allow selling beyond stock* are edited in `/admin` → Companies. The keys `tax_rate`,
`payment_terms_days` and `payment_methods` are read from the company's `settings` JSON but **have no screen yet**; set them
in the database or tinker, e.g.:

```php
$c = App\Models\Company::find(1);
$c->settings = array_merge($c->settings ?? [], ['tax_rate' => 15, 'payment_terms_days' => 14]);
$c->save();
```

## 5. Troubleshooting

| Symptom | Likely cause |
|---|---|
| Blank page / missing styles or charts | `npm run build` not run (or stale `public/build`) |
| Logos do not show | `php artisan storage:link` not run |
| "No company" page after login | The user has no company assignment, or all their companies are inactive |
| 419 page expired on forms | `APP_URL`/`SESSION_DOMAIN` mismatch or cookies blocked |
| Password reset mail never arrives | `MAIL_MAILER=log` — the mail is written to `storage/logs/laravel.log` |
| Deadlock / lock-wait errors under load | Retry the action; keep transactions short; check no long-running manual queries hold locks |
