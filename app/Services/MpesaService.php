<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Company;
use App\Models\MpesaTransaction;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * M-Pesa STK push (Safaricom Daraja): the customer gets a prompt on their phone, enters their PIN, and the
 * payment is recorded against the sale by the same PaymentService that handles cash and bank payments.
 *
 * Safety rules this class keeps:
 *  - the callback URL contains a secret per-push uuid, and the body must name the same CheckoutRequestID;
 *  - a payment is recorded once, however many times Safaricom repeats the callback (idempotency key = checkout id);
 *  - if money arrives that cannot be applied (sale cancelled, balance already cleared) it is kept as "needs review",
 *    never silently dropped or double-counted.
 */
class MpesaService
{
    private const SECRET_FIELDS = ['consumer_key', 'consumer_secret', 'passkey'];

    public function __construct(private PaymentService $payments) {}

    // ---- configuration ---------------------------------------------------------------------------

    /** The company's own M-Pesa details over the shared defaults; null until everything needed is present. */
    public function configFor(Company $company): ?array
    {
        $own = (array) $company->setting('mpesa', []);
        $defaults = (array) config('bizcore.mpesa', []);
        $cfg = [];

        foreach (['environment', 'type', 'shortcode'] as $key) {
            $cfg[$key] = filled($own[$key] ?? null) ? $own[$key] : ($defaults[$key] ?? null);
        }
        foreach (self::SECRET_FIELDS as $key) {
            $cfg[$key] = filled($own[$key] ?? null) ? $this->reveal($own[$key]) : ($defaults[$key] ?? null);
        }

        foreach (['shortcode', 'consumer_key', 'consumer_secret', 'passkey'] as $required) {
            if (blank($cfg[$required])) {
                return null;
            }
        }

        $cfg['environment'] = $cfg['environment'] === 'production' ? 'production' : 'sandbox';
        $cfg['type'] = $cfg['type'] === 'till' ? 'till' : 'paybill';

        return $cfg;
    }

    public function isConfigured(Company $company): bool
    {
        return $this->configFor($company) !== null;
    }

    /** Encrypts a secret for storing in the company settings. */
    public static function conceal(string $value): string
    {
        return Crypt::encryptString($value);
    }

    private function reveal(string $stored): ?string
    {
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null; // unreadable (for example the app key changed): treat as not set
        }
    }

    /** 0712 345 678, +254712345678, 254712345678 and 712345678 all become 254712345678. Null when it is not a Kenyan mobile. */
    public static function normalizePhone(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input);

        if (str_starts_with($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '254'.$digits;
        }

        return preg_match('/^254[17]\d{8}$/', $digits) ? $digits : null;
    }

    // ---- sending the prompt ------------------------------------------------------------------------

    public function stkPush(Sale $sale, string $phoneInput, string|int|float $amountInput, ?User $by = null): MpesaTransaction
    {
        $company = Company::findOrFail($sale->company_id);
        $cfg = $this->configFor($company) ?? throw new BusinessRuleException('M-Pesa is not set up for this company yet. An administrator can add the details on the Company page.');

        $phone = self::normalizePhone($phoneInput) ?? throw new BusinessRuleException('Enter a valid Safaricom number, for example 0712 345 678.');

        if (! is_numeric((string) $amountInput) || (float) $amountInput < 1 || floor((float) $amountInput) != (float) $amountInput) {
            throw new BusinessRuleException('M-Pesa takes whole shillings: enter an amount of 1 or more with no cents.');
        }
        $amount = Money::round($amountInput);

        if ($sale->isCancelled()) {
            throw new BusinessRuleException('This sale is cancelled, so it cannot be paid.');
        }
        if (Money::compare($amount, $sale->balance()) > 0) {
            throw new BusinessRuleException("The amount is more than the balance of {$sale->balance()}.");
        }

        // A second tap on the button while the first prompt is still on the customer's phone must not send another.
        $recent = MpesaTransaction::where('sale_id', $sale->id)->where('status', MpesaTransaction::PENDING)
            ->where('phone', $phone)->where('amount', $amount)->where('created_at', '>=', now()->subSeconds(90))->latest()->first();
        if ($recent) {
            return $recent;
        }

        try {
            $token = $this->accessToken($cfg);
        } catch (ConnectionException) {
            throw new BusinessRuleException('Could not reach M-Pesa. Please try again in a moment.');
        }

        $tx = (new MpesaTransaction)->forceFill([
            'uuid' => (string) Str::uuid(), 'company_id' => $company->id, 'sale_id' => $sale->id,
            'user_id' => $by?->id, 'phone' => $phone, 'amount' => $amount, 'status' => MpesaTransaction::PENDING,
        ]);
        $tx->save();

        $timestamp = now('Africa/Nairobi')->format('YmdHis');
        $reference = Str::limit($sale->invoice?->invoice_number ?? "SALE{$sale->id}", 12, '');

        try {
            $response = Http::withToken($token)->acceptJson()->timeout(25)
                ->post($this->baseUrl($cfg).'/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => $cfg['shortcode'],
                    'Password' => base64_encode($cfg['shortcode'].$cfg['passkey'].$timestamp),
                    'Timestamp' => $timestamp,
                    'TransactionType' => $cfg['type'] === 'till' ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
                    'Amount' => (int) $amount,
                    'PartyA' => $phone,
                    'PartyB' => $cfg['shortcode'],
                    'PhoneNumber' => $phone,
                    'CallBackURL' => route('mpesa.callback', ['uuid' => $tx->uuid], true),
                    'AccountReference' => $reference,
                    'TransactionDesc' => 'Invoice payment',
                ]);
        } catch (ConnectionException $e) {
            $this->fail($tx, 'Could not reach M-Pesa. Please try again.');
            throw new BusinessRuleException('Could not reach M-Pesa. Please try again in a moment.');
        }

        $body = $response->json() ?? [];

        if (! $response->successful() || (string) ($body['ResponseCode'] ?? '') !== '0') {
            $reason = $body['errorMessage'] ?? $body['ResponseDescription'] ?? 'M-Pesa refused the request.';
            Log::warning('M-Pesa STK push refused: '.$reason);
            $this->fail($tx, $reason);
            throw new BusinessRuleException("M-Pesa could not send the prompt: {$reason}");
        }

        $tx->forceFill([
            'merchant_request_id' => $body['MerchantRequestID'] ?? null,
            'checkout_request_id' => $body['CheckoutRequestID'] ?? null,
        ])->save();

        return $tx;
    }

    // ---- hearing back ------------------------------------------------------------------------------

    /** Safaricom's callback. Safe to call any number of times with the same payload. */
    public function handleCallback(MpesaTransaction $tx, array $payload): MpesaTransaction
    {
        $stk = data_get($payload, 'Body.stkCallback');

        if (! is_array($stk)) {
            throw new BusinessRuleException('Callback did not look like an M-Pesa result.');
        }
        if ($tx->checkout_request_id && ($stk['CheckoutRequestID'] ?? null) !== $tx->checkout_request_id) {
            throw new BusinessRuleException('Callback does not match this payment request.');
        }

        $items = collect(data_get($stk, 'CallbackMetadata.Item', []))->mapWithKeys(fn ($i) => [$i['Name'] ?? '' => $i['Value'] ?? null]);
        $code = (int) ($stk['ResultCode'] ?? -1);
        $receipt = $items->get('MpesaReceiptNumber');

        return DB::transaction(function () use ($tx, $stk, $payload, $items, $code, $receipt) {
            $locked = MpesaTransaction::whereKey($tx->id)->lockForUpdate()->firstOrFail();

            // Already settled: a repeat callback, or the status check got there first. Only fill in a missing receipt.
            if (! $locked->isPending()) {
                if ($locked->status === MpesaTransaction::SUCCESS && $receipt && ! $locked->mpesa_receipt) {
                    $locked->forceFill(['mpesa_receipt' => $receipt, 'callback_payload' => $payload])->save();
                    if ($locked->payment_id) {
                        DB::table('payments')->where('id', $locked->payment_id)->whereNull('reference')->update(['reference' => $receipt]);
                    }
                }

                return $locked;
            }

            $locked->forceFill(['result_code' => $code, 'result_desc' => $stk['ResultDesc'] ?? null, 'callback_payload' => $payload, 'completed_at' => now()]);

            if ($code !== 0) {
                $locked->forceFill(['status' => MpesaTransaction::FAILED])->save();

                return $locked;
            }

            $paid = Money::round($items->get('Amount') ?? $locked->amount);
            $locked->forceFill(['mpesa_receipt' => $receipt, 'amount' => $paid]);

            $this->settle($locked, $paid, $receipt);

            return $locked;
        });
    }

    /**
     * Asks Safaricom what became of a push whose callback has not arrived. Only a clear "failed or cancelled"
     * answer is acted on here; a success is left for the callback, which carries the receipt number.
     */
    public function checkStatus(MpesaTransaction $tx): MpesaTransaction
    {
        if (! $tx->isPending() || ! $tx->checkout_request_id) {
            return $tx;
        }

        $company = Company::find($tx->company_id);
        $cfg = $company ? $this->configFor($company) : null;
        if (! $cfg) {
            return $tx;
        }

        try {
            $timestamp = now('Africa/Nairobi')->format('YmdHis');
            $body = Http::withToken($this->accessToken($cfg))->acceptJson()->timeout(20)
                ->post($this->baseUrl($cfg).'/mpesa/stkpushquery/v1/query', [
                    'BusinessShortCode' => $cfg['shortcode'],
                    'Password' => base64_encode($cfg['shortcode'].$cfg['passkey'].$timestamp),
                    'Timestamp' => $timestamp,
                    'CheckoutRequestID' => $tx->checkout_request_id,
                ])->json() ?? [];
        } catch (Throwable) {
            return $tx; // try again on the next poll
        }

        // While the customer is still deciding, Safaricom answers with an error instead of a result code.
        if (! array_key_exists('ResultCode', $body)) {
            return $tx;
        }

        $code = (int) $body['ResultCode'];
        if ($code !== 0) {
            return $this->handleCallback($tx, ['Body' => ['stkCallback' => [
                'CheckoutRequestID' => $tx->checkout_request_id, 'ResultCode' => $code, 'ResultDesc' => $body['ResultDesc'] ?? 'Not completed',
            ]]]);
        }

        return $tx;
    }

    // ---- internals ---------------------------------------------------------------------------------

    /** Put the money against the sale, as the person who pressed the button, in the right company. */
    private function settle(MpesaTransaction $tx, string $amount, ?string $receipt): void
    {
        $company = Company::findOrFail($tx->company_id);
        $previousUser = Auth::user();

        try {
            app(CurrentCompany::class)->runAs($company, function () use ($tx, $amount, $receipt) {
                if ($tx->user_id && ($user = User::find($tx->user_id))) {
                    Auth::setUser($user);
                }

                $sale = Sale::findOrFail($tx->sale_id);
                $payment = $this->payments->record($sale, $amount, 'mpesa', today(), $receipt, 'mpesa:'.($tx->checkout_request_id ?: $tx->uuid));
                $tx->forceFill(['status' => MpesaTransaction::SUCCESS, 'payment_id' => $payment->id])->save();
            });
        } catch (BusinessRuleException $e) {
            // The customer has paid but the sale cannot take it. Keep the record so someone can refund or reallocate.
            Log::warning("M-Pesa {$receipt} needs review: ".$e->getMessage());
            $tx->forceFill(['status' => MpesaTransaction::NEEDS_REVIEW, 'result_desc' => $e->getMessage()])->save();
        } finally {
            $previousUser ? Auth::setUser($previousUser) : null;
        }
    }

    private function fail(MpesaTransaction $tx, string $reason): void
    {
        $tx->forceFill(['status' => MpesaTransaction::FAILED, 'result_desc' => Str::limit($reason, 250), 'completed_at' => now()])->save();
    }

    private function baseUrl(array $cfg): string
    {
        return $cfg['environment'] === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
    }

    private function accessToken(array $cfg): string
    {
        $response = Http::withBasicAuth($cfg['consumer_key'], $cfg['consumer_secret'])->acceptJson()->timeout(20)
            ->get($this->baseUrl($cfg).'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

        $token = $response->json('access_token');
        if (! $response->successful() || ! $token) {
            throw new BusinessRuleException('M-Pesa rejected the saved consumer key and secret. Check them on the Company page.');
        }

        return $token;
    }
}
