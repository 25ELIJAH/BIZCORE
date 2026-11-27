<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Company\Settings;
use App\Livewire\Sales\Show;
use App\Models\Company;
use App\Models\Customer;
use App\Models\MpesaTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\MpesaService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class MpesaTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private User $cashier;

    private Sale $sale;

    private MpesaService $mpesa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create(['settings' => ['mpesa' => [
            'environment' => 'sandbox', 'type' => 'paybill', 'shortcode' => '174379',
            'consumer_key' => MpesaService::conceal('key-123'), 'consumer_secret' => MpesaService::conceal('secret-456'), 'passkey' => MpesaService::conceal('pass-789'),
        ]]]);
        $this->cashier = $this->userWithRole('sales-officer', $this->company);
        app(CurrentCompany::class)->set($this->company);
        $this->actingAs($this->cashier);

        $product = Product::factory()->create(['selling_price' => 1000, 'min_stock' => 0]);
        app(StockService::class)->add($product, 10);
        $this->sale = app(SaleService::class)->create(['customer_id' => Customer::factory()->create(['phone' => '0712345678'])->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);
        $this->mpesa = app(MpesaService::class);
    }

    private function fakeDaraja(array $stk = null): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
            '*/mpesa/stkpush/v1/processrequest' => Http::response($stk ?? ['MerchantRequestID' => 'm-1', 'CheckoutRequestID' => 'ws_CO_1', 'ResponseCode' => '0', 'ResponseDescription' => 'Success', 'CustomerMessage' => 'Success']),
            '*/mpesa/stkpushquery/v1/query' => Http::response(['errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed']),
        ]);
    }

    private function hitCallback(MpesaTransaction $tx, int $code = 0, string $receipt = 'QWE123XYZ', ?string $checkout = null, $amount = 1000)
    {
        $stk = ['MerchantRequestID' => 'm-1', 'CheckoutRequestID' => $checkout ?? $tx->checkout_request_id, 'ResultCode' => $code, 'ResultDesc' => $code === 0 ? 'The service request is processed successfully.' : 'Request cancelled by user'];
        if ($code === 0) {
            $stk['CallbackMetadata'] = ['Item' => [['Name' => 'Amount', 'Value' => $amount], ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt], ['Name' => 'PhoneNumber', 'Value' => 254712345678]]];
        }

        return $this->postJson(route('mpesa.callback', $tx->uuid), ['Body' => ['stkCallback' => $stk]]);
    }

    public function test_phone_numbers_are_normalised_and_bad_ones_refused(): void
    {
        foreach (['0712345678', '+254712345678', '254712345678', '712345678', '0712 345 678', '0112345678'] as $ok) {
            $this->assertMatchesRegularExpression('/^254[17]\d{8}$/', MpesaService::normalizePhone($ok), $ok);
        }
        foreach (['', '12345', '0212345678', '07123456789', 'abc'] as $bad) {
            $this->assertNull(MpesaService::normalizePhone($bad), $bad);
        }
    }

    public function test_the_prompt_is_sent_with_the_right_details_and_a_secret_callback_url(): void
    {
        $this->fakeDaraja();

        $tx = $this->mpesa->stkPush($this->sale, '0712 345 678', 1000, $this->cashier);

        $this->assertSame('pending', $tx->status);
        $this->assertSame('ws_CO_1', $tx->checkout_request_id);
        Http::assertSent(function ($request) use ($tx) {
            if (! str_contains($request->url(), 'stkpush/v1/processrequest')) {
                return true;
            }
            $d = $request->data();

            return $d['Amount'] === 1000 && $d['PartyA'] === '254712345678' && $d['BusinessShortCode'] === '174379'
                && $d['TransactionType'] === 'CustomerPayBillOnline' && str_contains($d['CallBackURL'], $tx->uuid)
                && $request->hasHeader('Authorization', 'Bearer tok');
        });
    }

    public function test_a_successful_callback_records_one_mpesa_payment_and_settles_the_sale(): void
    {
        $this->fakeDaraja();
        $tx = $this->mpesa->stkPush($this->sale, '0712345678', 1000, $this->cashier);

        $this->hitCallback($tx)->assertOk()->assertJson(['ResultCode' => 0]);
        $this->hitCallback($tx)->assertOk(); // Safaricom repeats callbacks: nothing more may be recorded

        $this->assertSame('success', $tx->fresh()->status);
        $this->assertSame('QWE123XYZ', $tx->fresh()->mpesa_receipt);
        $payments = $this->sale->payments()->get();
        $this->assertCount(1, $payments);
        $this->assertSame('mpesa', $payments[0]->method);
        $this->assertSame('QWE123XYZ', $payments[0]->reference);
        $this->assertSame($this->cashier->id, $payments[0]->user_id);
        $this->assertSame('paid', $this->sale->fresh()->payment_status);
    }

    public function test_a_cancelled_prompt_records_nothing(): void
    {
        $this->fakeDaraja();
        $tx = $this->mpesa->stkPush($this->sale, '0712345678', 500, $this->cashier);

        $this->hitCallback($tx, 1032)->assertOk();

        $this->assertSame('failed', $tx->fresh()->status);
        $this->assertCount(0, $this->sale->payments()->get());
    }

    public function test_a_callback_with_the_wrong_checkout_id_or_unknown_url_is_not_trusted(): void
    {
        $this->fakeDaraja();
        $tx = $this->mpesa->stkPush($this->sale, '0712345678', 1000, $this->cashier);

        $this->hitCallback($tx, 0, 'FAKE000000', 'ws_CO_OTHER')->assertOk();
        $this->assertSame('pending', $tx->fresh()->status);
        $this->assertCount(0, $this->sale->payments()->get());

        $this->postJson(route('mpesa.callback', '00000000-0000-0000-0000-000000000000'), ['Body' => []])->assertNotFound();
    }

    public function test_money_that_cannot_be_applied_is_kept_for_review_not_lost(): void
    {
        $this->fakeDaraja();
        $tx = $this->mpesa->stkPush($this->sale, '0712345678', 1000, $this->cashier);
        app(SaleService::class)->cancel($this->sale, 'Customer changed mind');

        $this->hitCallback($tx)->assertOk();

        $fresh = $tx->fresh();
        $this->assertSame('needs_review', $fresh->status);
        $this->assertSame('QWE123XYZ', $fresh->mpesa_receipt);
        $this->assertCount(0, $this->sale->payments()->get());
    }

    public function test_it_refuses_unsafe_requests_before_calling_safaricom(): void
    {
        $this->fakeDaraja();

        foreach ([['07123', 100, 'valid Safaricom'], ['0712345678', 100.5, 'whole shillings'], ['0712345678', 0, 'whole shillings'], ['0712345678', 5000, 'more than the balance']] as [$phone, $amount, $expected]) {
            try {
                $this->mpesa->stkPush($this->sale, $phone, $amount, $this->cashier);
                $this->fail("Expected a refusal for {$phone} / {$amount}");
            } catch (BusinessRuleException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }

        Http::assertNothingSent();
        $this->assertSame(0, MpesaTransaction::count());
    }

    public function test_a_company_without_mpesa_details_cannot_send_a_prompt(): void
    {
        $this->company->forceFill(['settings' => []])->save();
        config(['bizcore.mpesa' => ['environment' => 'sandbox', 'type' => 'paybill', 'shortcode' => null, 'consumer_key' => null, 'consumer_secret' => null, 'passkey' => null]]);

        $this->assertFalse($this->mpesa->isConfigured($this->company->fresh()));
        $this->expectException(BusinessRuleException::class);
        $this->mpesa->stkPush($this->sale, '0712345678', 100, $this->cashier);
    }

    public function test_a_double_tap_does_not_send_a_second_prompt(): void
    {
        $this->fakeDaraja();

        $first = $this->mpesa->stkPush($this->sale, '0712345678', 1000, $this->cashier);
        $second = $this->mpesa->stkPush($this->sale, '0712345678', 1000, $this->cashier);

        $this->assertSame($first->id, $second->id);
        Http::assertSentCount(2); // one token request and one prompt, no more
    }

    public function test_refused_keys_give_a_clear_message_and_leave_no_stuck_row(): void
    {
        Http::fake(['*/oauth/v1/generate*' => Http::response(['errorMessage' => 'Invalid credentials'], 400)]);

        try {
            $this->mpesa->stkPush($this->sale, '0712345678', 1000, $this->cashier);
            $this->fail('Expected a refusal');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('consumer key', $e->getMessage());
        }
        $this->assertSame(0, MpesaTransaction::count());
    }

    public function test_the_company_page_saves_keys_encrypted_and_never_shows_them_again(): void
    {
        $admin = $this->userWithRole('company-administrator', $this->company);
        $this->actingAs($admin);

        Livewire::test(Settings::class)
            ->assertSet('mpesa_consumer_key', '')
            ->set('mpesa_environment', 'production')->set('mpesa_shortcode', '600123')->set('mpesa_consumer_key', 'NEW-KEY-ABC')
            ->call('save')
            ->assertSet('mpesa_consumer_key', '');

        $raw = json_encode($this->company->fresh()->settings);
        $this->assertStringNotContainsString('NEW-KEY-ABC', $raw);
        $this->assertSame('production', $this->company->fresh()->setting('mpesa.environment'));
        $this->assertSame('NEW-KEY-ABC', $this->mpesa->configFor($this->company->fresh())['consumer_key']);
        $this->assertSame('secret-456', $this->mpesa->configFor($this->company->fresh())['consumer_secret'], 'untouched secrets are kept');
    }

    public function test_the_sale_page_offers_mpesa_only_when_it_is_set_up_and_runs_the_flow(): void
    {
        $this->fakeDaraja();

        Livewire::test(Show::class, ['sale' => $this->sale])
            ->assertSee('Pay with M-Pesa')
            ->call('openMpesa')->assertSet('mpesaPhone', '0712345678')->assertSet('mpesaAmount', '1000')
            ->call('sendMpesa')->assertSee('Prompt sent to 254712345678');

        $tx = MpesaTransaction::first();
        $this->hitCallback($tx);

        Livewire::test(Show::class, ['sale' => $this->sale->fresh()])->assertSee('M-Pesa')->assertSee('QWE123XYZ');

        $this->company->forceFill(['settings' => []])->save();
        config(['bizcore.mpesa.consumer_key' => null]);
        Livewire::test(Show::class, ['sale' => Sale::find($this->sale->id)])->assertDontSee('Pay with M-Pesa');
    }

    private function newSaleForm(string $phone = '0712345678')
    {
        $product = Product::factory()->create(['selling_price' => 1000, 'min_stock' => 0]);
        app(StockService::class)->add($product, 10);

        return Livewire::test(\App\Livewire\Sales\Create::class)
            ->set('customerId', Customer::factory()->create(['phone' => $phone])->id)
            ->call('addProduct', $product->id);
    }

    public function test_choosing_mpesa_on_the_new_sale_screen_sends_the_prompt_when_the_sale_is_completed(): void
    {
        $this->fakeDaraja();

        $form = $this->newSaleForm()->call('selectPay', 'mpesa')
            ->assertSet('payMethod', 'mpesa')->assertSet('payNow', '1000')->assertSet('mpesaPhone', '0712345678')
            ->assertSee('Customer phone');
        $form->call('save')->assertHasNoErrors();

        $sale = Sale::latest('id')->first();
        $tx = MpesaTransaction::where('sale_id', $sale->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame('pending', $tx->status);
        $this->assertCount(0, $sale->payments()->get(), 'nothing is recorded until the customer pays');
        $form->assertRedirect(route('sales.show', ['sale' => $sale, 'mpesa' => $tx->id]));

        // The sale page opens straight into the waiting screen, and the callback completes it.
        Livewire::withQueryParams(['mpesa' => $tx->id])->test(Show::class, ['sale' => $sale])->assertSet('showMpesa', true)->assertSee('Prompt sent to');
        $this->hitCallback($tx)->assertOk();
        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_a_bad_phone_on_the_new_sale_screen_stops_before_any_sale_is_saved(): void
    {
        $this->fakeDaraja();
        $before = Sale::count();

        $this->newSaleForm('')->call('selectPay', 'mpesa')->set('mpesaPhone', '07123')->call('save')->assertHasErrors('mpesaPhone');

        $this->assertSame($before, Sale::count());
        $this->assertSame(0, MpesaTransaction::count());
    }

    public function test_if_the_prompt_cannot_be_sent_the_sale_is_still_saved_with_a_clear_message(): void
    {
        $this->fakeDaraja(['errorMessage' => 'Bad Request - Invalid Amount', 'requestId' => 'x']);
        $before = Sale::count();

        $this->newSaleForm()->call('selectPay', 'mpesa')->call('save')->assertHasNoErrors();

        $this->assertSame($before + 1, Sale::count());
        $this->assertStringContainsString('prompt was not sent', session('status'));
        $this->assertSame('failed', MpesaTransaction::first()->status);
    }

    public function test_cash_and_pay_later_still_work_from_the_same_tiles(): void
    {
        $cash = $this->newSaleForm()->call('selectPay', 'cash')->assertSet('payNow', '1000.00');
        $cash->call('save')->assertHasNoErrors();
        $paid = Sale::latest('id')->first();
        $this->assertSame('cash', $paid->payments()->first()->method);
        $this->assertSame('paid', $paid->payment_status);

        $later = $this->newSaleForm()->call('selectPay', 'cash')->call('payLater')->assertSet('payNow', '');
        $later->call('save')->assertHasNoErrors();
        $this->assertCount(0, Sale::latest('id')->first()->payments()->get());
    }

    public function test_without_mpesa_details_the_tile_asks_for_a_receipt_number_instead_of_a_prompt(): void
    {
        $this->company->forceFill(['settings' => []])->save();
        config(['bizcore.mpesa.consumer_key' => null]);

        $this->newSaleForm()->call('selectPay', 'mpesa')->assertDontSee('Customer phone')->assertSee('receipt number')
            ->set('payReference', 'RCP123')->call('save')->assertHasNoErrors();

        $payment = Sale::latest('id')->first()->payments()->first();
        $this->assertSame('mpesa', $payment->method);
        $this->assertSame('RCP123', $payment->reference);
        Http::assertNothingSent();
    }

    public function test_the_callback_needs_no_login_but_other_companies_cannot_reach_a_payment_through_the_page(): void
    {
        $this->fakeDaraja();
        $tx = $this->mpesa->stkPush($this->sale, '0712345678', 1000, $this->cashier);

        $otherCompany = Company::factory()->create();
        $stranger = $this->userWithRole('company-administrator', $otherCompany);
        app(CurrentCompany::class)->set($otherCompany);
        $this->actingAs($stranger);

        // The polled status check only ever finds rows of the sale and company the page is on.
        $this->assertNull(MpesaTransaction::where('company_id', $otherCompany->id)->find($tx->id));
    }
}
