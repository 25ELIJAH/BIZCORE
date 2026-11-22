<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Livewire\Activity\Index as ActivityIndex;
use App\Livewire\Sales\Show;
use App\Mail\InvoiceMail;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class InvoiceEmailAndActivityTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create(['settings' => ['bank_name' => 'Equity Bank', 'bank_account_number' => '0123456789', 'website' => 'www.acme.test']]);
    }

    private function makeSale(?string $email): Sale
    {
        return app(CurrentCompany::class)->runAs($this->company, function () use ($email) {
            $this->actingAs($this->userWithRole('company-administrator', $this->company));
            $customer = Customer::factory()->create(['email' => $email]);
            $p = Product::factory()->create(['selling_price' => 50]);
            app(StockService::class)->add($p, 10, StockMovementType::Purchase, 20);

            return app(SaleService::class)->create(['customer_id' => $customer->id, 'items' => [['product_id' => $p->id, 'quantity' => 2]]]);
        });
    }

    private function actAsAdmin(): void
    {
        $this->actingAs($this->userWithRole('company-administrator', $this->company));
        app(CurrentCompany::class)->set($this->company);
    }

    public function test_invoice_is_emailed_as_a_pdf_and_logged(): void
    {
        Mail::fake();
        $sale = $this->makeSale('buyer@example.test');
        $this->actAsAdmin();

        Livewire::test(Show::class, ['sale' => $sale])->call('emailInvoice', false)->assertSee('emailed to buyer@example.test');

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $m) => $m->hasTo('buyer@example.test') && ! $m->reminder);
        $this->assertDatabaseHas('activity_logs', ['action' => 'invoice.emailed', 'company_id' => $this->company->id]);
    }

    public function test_reminder_button_only_shows_when_overdue_and_customer_without_email_is_refused(): void
    {
        Mail::fake();
        $sale = $this->makeSale(null);
        $this->actAsAdmin();

        Livewire::test(Show::class, ['sale' => $sale])->assertDontSee('Send reminder')->call('emailInvoice', false)->assertSee('no email address');
        Mail::assertNothingSent();

        $sale->forceFill(['due_date' => today()->subDays(5)])->save();
        Livewire::test(Show::class, ['sale' => $sale->fresh()])->assertSee('Send reminder');
    }

    public function test_the_mail_carries_the_invoice_pdf_and_bank_details(): void
    {
        $sale = $this->makeSale('buyer@example.test');
        $this->actAsAdmin();

        $mail = new InvoiceMail($sale->fresh(['invoice', 'customer']), $this->company, true);

        $this->assertStringStartsWith('Reminder: invoice', $mail->envelope()->subject);
        $this->assertCount(1, $mail->attachments());
        $mail->assertSeeInHtml('0123456789');
    }

    public function test_activity_page_lists_only_this_companys_entries_and_needs_permission(): void
    {
        $other = Company::factory()->create();
        ActivityLog::create(['company_id' => $this->company->id, 'action' => 'invoice.emailed', 'description' => 'Mine', 'created_at' => now()]);
        ActivityLog::create(['company_id' => $other->id, 'action' => 'invoice.emailed', 'description' => 'Theirs', 'created_at' => now()]);

        $this->actAsAdmin();
        Livewire::test(ActivityIndex::class)->assertSee('Mine')->assertDontSee('Theirs');

        $this->actingAs($this->userWithRole('sales-officer', $this->company));
        app(CurrentCompany::class)->set($this->company);
        Livewire::test(ActivityIndex::class)->assertForbidden();
    }
}
