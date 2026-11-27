# M-Pesa STK push: setup

Lets a cashier press **Pay with M-Pesa** on a sale. The customer gets a prompt on their phone, enters their PIN, and the
payment is recorded on the invoice automatically (method "M-Pesa", reference = the M-Pesa receipt number).

## 1. Create the database table (once)

```powershell
php artisan migrate --force
```

This adds `mpesa_transactions`: one row per prompt, kept as the audit trail.

## 2. Get Daraja credentials

1. Sign in at https://developer.safaricom.co.ke and create an app with the **Lipa na M-Pesa** product.
2. Copy the **Consumer key** and **Consumer secret**.
3. For testing (Sandbox) use the shortcode and passkey shown on the "Lipa na M-Pesa Online" simulator page.
4. For real money (Production) use your own paybill or till number and the passkey Safaricom issued (Go-Live process).

## 3. Enter them in BIZCORE

Sign in as a company administrator, open **Company**, and fill **M-Pesa payments**:

- Mode: **Sandbox** while testing, **Production** only after Safaricom approves your Go-Live.
- Receive on: Paybill or Till number, and the number itself.
- Consumer key, Consumer secret and Passkey. They are stored encrypted and never shown again.

When the badge says **Ready**, a green **Pay with M-Pesa** button appears on unpaid sales.

Each company keeps its own details. A shared fallback can be set with the environment variables
`MPESA_ENV`, `MPESA_TYPE`, `MPESA_SHORTCODE`, `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_PASSKEY`.

## 4. How it stays safe

- The callback address contains a secret per-prompt code, and the body must name the same CheckoutRequestID.
- A repeated callback never records a second payment.
- If money arrives that cannot be applied (for example the sale was cancelled meanwhile) it is kept as
  **needs review**, never dropped or double counted.
- Whole shillings only, never more than the invoice balance, Safaricom numbers only.
- `APP_URL` must be the public https address of the site, because Safaricom has to reach the callback.
