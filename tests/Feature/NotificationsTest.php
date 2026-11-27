<?php

namespace Tests\Feature;

use App\Livewire\Company\Settings;
use App\Livewire\Notifications\Inbox;
use App\Mail\BusinessEventMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\LeaveService;
use App\Services\PurchaseOrderService;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Notifier;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->admin = $this->userWithRole('company-administrator', $this->company);
        $this->staff = $this->userWithRole('employee', $this->company);
        app(CurrentCompany::class)->set($this->company);
        Mail::fake();
    }

    private function noticesFor(User $user, ?string $type = null)
    {
        return DatabaseNotification::where('notifiable_id', $user->id)->when($type, fn ($q) => $q->where('type', $type))->get();
    }

    private function sell(User $by): void
    {
        $this->actingAs($by);
        $product = Product::factory()->create(['selling_price' => 100, 'min_stock' => 0]);
        app(StockService::class)->add($product, 10);
        app(SaleService::class)->create(['customer_id' => Customer::factory()->create()->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]]);
    }

    public function test_a_sale_tells_the_admin_in_the_bell_and_by_email_but_not_the_seller(): void
    {
        $seller = $this->userWithRole('sales-officer', $this->company);
        $this->sell($seller);

        $notices = $this->noticesFor($this->admin, 'sale.created');
        $this->assertCount(1, $notices);
        $this->assertStringContainsString('New sale', $notices[0]->data['title']);
        $this->assertSame($this->company->id, (int) $notices[0]->company_id);
        $this->assertCount(0, $this->noticesFor($seller, 'sale.created'));
        Mail::assertSent(BusinessEventMail::class, fn ($m) => $m->hasTo($this->admin->email));
    }

    public function test_an_email_can_be_switched_off_per_event_but_the_bell_still_rings(): void
    {
        $this->company->forceFill(['settings' => ['notifications_email' => ['sale_created' => false]]])->save();

        $this->sell($this->userWithRole('sales-officer', $this->company));

        $this->assertCount(1, $this->noticesFor($this->admin, 'sale.created'));
        Mail::assertNothingSent();
    }

    public function test_a_leave_request_reaches_approvers_and_the_decision_reaches_the_employee(): void
    {
        $hr = $this->userWithRole('hr-officer', $this->company);
        $this->actingAs($hr);
        $employee = app(EmployeeService::class)->save(['first_name' => 'Amina', 'last_name' => 'Otieno', 'hire_date' => '2024-01-01', 'user_id' => $this->staff->id], null, true);

        $this->actingAs($this->staff);
        $monday = today()->next('Monday')->addWeeks(2);
        $request = app(LeaveService::class)->request($employee, 'annual', $monday, $monday->copy()->addDay());

        $this->assertCount(1, $this->noticesFor($hr, 'leave.requested'));
        $this->assertCount(1, $this->noticesFor($this->admin, 'leave.requested'));
        $this->assertCount(0, $this->noticesFor($this->staff, 'leave.requested'));

        $this->actingAs($hr);
        app(LeaveService::class)->approve($request, 'Enjoy');

        $decided = $this->noticesFor($this->staff, 'leave.decided');
        $this->assertCount(1, $decided);
        $this->assertSame('Leave approved', $decided[0]->data['title']);
        $this->assertCount(0, $this->noticesFor($hr, 'leave.decided'), 'the person deciding is not told');
    }

    public function test_a_purchase_order_asks_approvers_and_answers_the_buyer(): void
    {
        $buyer = $this->userWithRole('procurement-officer', $this->company);
        $this->actingAs($buyer);
        $orders = app(PurchaseOrderService::class);
        $order = $orders->saveDraft(['supplier_id' => Supplier::factory()->create()->id, 'items' => [['product_id' => Product::factory()->create()->id, 'quantity' => 4, 'unit_cost' => 5]]]);
        $orders->submit($order);

        $this->assertCount(1, $this->noticesFor($this->admin, 'purchase_order.submitted'));
        $this->assertCount(0, $this->noticesFor($buyer, 'purchase_order.submitted'));

        $this->actingAs($this->admin);
        $orders->reject($order, 'Quantity too low');

        $back = $this->noticesFor($buyer, 'purchase_order.decided');
        $this->assertCount(1, $back);
        $this->assertStringContainsString('sent back', $back[0]->data['title']);
        $this->assertStringContainsString('Quantity too low', $back[0]->data['body']);
    }

    public function test_low_stock_is_announced_once_when_it_crosses_the_minimum(): void
    {
        $this->actingAs($this->userWithRole('sales-officer', $this->company));
        $product = Product::factory()->create(['min_stock' => 5]);
        $stock = app(StockService::class);
        $stock->add($product, 8);

        $stock->deduct($product, 2); // 6, still above
        $this->assertCount(0, $this->noticesFor($this->admin, 'stock.low'));

        $stock->deduct($product, 2); // 4, crossed
        $stock->deduct($product, 1); // 3, already low
        $this->assertCount(1, $this->noticesFor($this->admin, 'stock.low'));
    }

    public function test_a_broken_mail_server_never_breaks_the_business_action(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->sell($this->userWithRole('sales-officer', $this->company));

        $this->assertCount(1, $this->noticesFor($this->admin, 'sale.created'));
    }

    public function test_people_only_see_notices_for_the_company_they_are_in(): void
    {
        $other = Company::factory()->create();
        $this->admin->companies()->attach($other->id, ['role_id' => $this->admin->roleIn($this->company)->id]);
        $this->sell($this->userWithRole('sales-officer', $this->company));
        DatabaseNotification::first()->replicate()->forceFill(['id' => (string) Str::uuid(), 'company_id' => $other->id, 'data' => ['title' => 'Other company news', 'body' => '']])->save();

        $this->actingAs($this->admin);
        Livewire::test(Inbox::class)->assertSee('New sale')->assertDontSee('Other company news');
    }

    public function test_opening_a_notice_marks_it_read_and_mark_all_clears_the_rest(): void
    {
        $this->sell($this->userWithRole('sales-officer', $this->company));
        $this->sell($this->userWithRole('sales-officer', $this->company));
        $this->actingAs($this->admin);
        $first = $this->noticesFor($this->admin)->first();

        Livewire::test(Inbox::class)->call('open', $first->id)->assertRedirect();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(1, $this->admin->unreadNotifications()->count());

        Livewire::test(Inbox::class)->call('markAllRead');
        $this->assertSame(0, $this->admin->unreadNotifications()->count());
    }

    public function test_someone_cannot_open_a_notice_that_belongs_to_another_person(): void
    {
        $this->sell($this->userWithRole('sales-officer', $this->company));
        $theirs = $this->noticesFor($this->admin)->first();

        $this->actingAs($this->staff);
        Livewire::test(Inbox::class)->call('open', $theirs->id)->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_administrators_choose_which_events_are_emailed(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(Settings::class)->set('emails.sale_created', false)->set('emails.stock_low', false)->call('save');

        $company = $this->company->fresh();
        $this->assertFalse(Notifier::emailEnabled($company, 'sale.created'));
        $this->assertFalse(Notifier::emailEnabled($company, 'stock.low'));
        $this->assertTrue(Notifier::emailEnabled($company, 'leave.requested'));
    }

    public function test_the_notifications_page_renders_for_any_member(): void
    {
        $this->actingAs($this->staff)->get(route('notifications.index'))->assertOk()->assertSee('Notifications');
    }
}
