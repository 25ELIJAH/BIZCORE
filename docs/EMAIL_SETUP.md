# Email setup (password resets, invoices, reminders)

BIZCORE sends email for **password reset links**, **invoices** and **payment reminders**. Until a real mail service is
configured it only writes emails to the log (`MAIL_MAILER=log`), so nobody receives them.

## 1. Pick a mail service

Any SMTP provider works. Free tiers are enough to start:

| Provider | Host | Port | Notes |
|---|---|---|---|
| Brevo (Sendinblue) | `smtp-relay.brevo.com` | 587 | Create an SMTP key in *SMTP & API* |
| Resend | `smtp.resend.com` | 465 (`MAIL_SCHEME=smtps`) or 587 | Username is `resend`, password is an API key; verify your domain |
| Gmail | `smtp.gmail.com` | 587 | Needs 2-step verification and an *App password* (not your normal password) |

## 2. Add these environment variables in Vercel

Project → Settings → Environment Variables (Production), then redeploy:

```
MAIL_MAILER=smtp
MAIL_HOST=<host from the table>
MAIL_PORT=587
MAIL_USERNAME=<your SMTP username>
MAIL_PASSWORD=<your SMTP password or API key>
MAIL_FROM_ADDRESS=<an address your provider allows, e.g. no-reply@yourdomain.com>
MAIL_FROM_NAME="BIZCORE ERP"
```

Leave `MAIL_SCHEME` empty for port 587 (STARTTLS). Use `smtps` with port 465.

## 3. Test it

Locally (with the same values in `.env`):

```
php artisan bizcore:mail-test you@example.com
```

On the live site, use **Forgot password?** on the sign-in page with a real user's email and check the inbox (and spam).

## If someone cannot receive email

An administrator can set a new password for them in the admin panel (`/admin` → Users → edit → Password). Once signed
in, every user can change their own password from **My account** (click the avatar at the top right).
