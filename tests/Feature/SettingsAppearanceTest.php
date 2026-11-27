<?php

namespace Tests\Feature;

use App\Livewire\Company\Appearance;
use App\Models\Company;
use App\Models\CompanyAsset;
use App\Models\User;
use App\Support\Appearance as Look;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class SettingsAppearanceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->admin = $this->userWithRole('company-administrator', $this->company);
        app(CurrentCompany::class)->set($this->company);
        $this->actingAs($this->admin);
    }

    /** A real 2x2 PNG as a browser would send it. */
    private function png(): string
    {
        return 'data:image/png;base64,'.base64_encode((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFElEQVR4nGP8z8Dwn4GBgYGJAQoAHhgCAh6X4CYAAAAASUVORK5CYII='));
    }

    public function test_only_administrators_can_open_the_settings_page(): void
    {
        $this->get(route('company.appearance'))->assertOk()->assertSee('Company logo')->assertSee('See-through interface');

        $this->actingAs($this->userWithRole('sales-officer', $this->company));
        $this->get(route('company.appearance'))->assertForbidden();
    }

    public function test_the_logo_is_validated_saved_served_and_shown_in_the_top_bar(): void
    {
        $page = Livewire::test(Appearance::class);

        $page->call('uploadLogo', 'data:image/svg+xml;base64,'.base64_encode('<svg onload="alert(1)"/>'))->assertHasErrors('logo');
        $page->call('uploadLogo', 'data:image/png;base64,'.base64_encode('not really a picture'))->assertHasErrors('logo');
        $page->call('uploadLogo', 'hello')->assertHasErrors('logo');
        $this->assertSame(0, CompanyAsset::count());

        $page->call('uploadLogo', $this->png())->assertHasNoErrors();
        $this->assertSame('image/png', CompanyAsset::where('kind', 'logo')->value('mime'));

        $this->get(route('company.asset', 'logo'))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('home'))->assertSee('Powered by BizCore', false)->assertSee(route('company.asset', 'logo'), false);

        $page->call('removeLogo');
        $this->assertSame(0, CompanyAsset::count());
        $this->get(route('company.asset', 'logo'))->assertNotFound();
    }

    public function test_the_logo_can_be_hidden_from_the_top_bar_but_stays_on_file(): void
    {
        Livewire::test(Appearance::class)->call('uploadLogo', $this->png())->set('showLogo', false);

        $this->get(route('home'))->assertDontSee('Powered by BizCore', false);
        $this->assertSame(1, CompanyAsset::count());
    }

    public function test_another_company_never_sees_this_logo(): void
    {
        Livewire::test(Appearance::class)->call('uploadLogo', $this->png());

        $other = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $other));
        app(CurrentCompany::class)->set($other);

        $this->get(route('company.asset', 'logo'))->assertNotFound();
    }

    public function test_an_oversized_picture_is_refused(): void
    {
        $big = 'data:image/png;base64,'.base64_encode(str_repeat('a', 800_000));

        Livewire::test(Appearance::class)->call('uploadLogo', $big)->assertHasErrors('logo');
    }

    public function test_the_glass_toggle_and_strength_are_saved_and_applied_to_every_page(): void
    {
        $page = Livewire::test(Appearance::class)->set('glass', true)->assertSet('background', 'sunset')->assertDispatched('appearance-changed')->set('level', 80);

        $look = Look::for(Company::find($this->company->id));
        $this->assertTrue($look['glass']);
        $this->assertSame(80, $look['level']);
        $this->assertSame('0.520', $look['alpha']);

        $this->get(route('home'))->assertSee('theme-glass', false)->assertSee('--glass-alpha: 0.520', false)->assertSee('auth-bg.svg', false);

        $page->set('glass', false);
        $this->get(route('home'))->assertDontSee('theme-glass', false);
    }

    public function test_backgrounds_can_be_picked_and_a_custom_one_uploaded(): void
    {
        $page = Livewire::test(Appearance::class)->call('chooseBackground', 'aurora');
        $this->get(route('home'))->assertSee('bg-aurora.svg', false);

        $page->call('chooseBackground', 'custom')->assertSet('background', 'aurora'); // nothing uploaded yet: ignored

        $page->call('uploadBackground', $this->png())->assertHasNoErrors()->assertSet('background', 'custom');
        $this->get(route('home'))->assertSee(route('company.asset', 'background'), false);
        $this->get(route('company.asset', 'background'))->assertOk();

        $page->call('removeBackground')->assertSet('background', 'plain');
        $this->get(route('home'))->assertDontSee(route('company.asset', 'background'), false);
    }

    public function test_an_unknown_background_is_refused(): void
    {
        Livewire::test(Appearance::class)->call('chooseBackground', '../../etc/passwd')->assertStatus(422);
    }

    public function test_the_invoice_pdf_uses_the_uploaded_logo(): void
    {
        Livewire::test(Appearance::class)->call('uploadLogo', $this->png());

        $pdf = new \ReflectionMethod(\App\Services\InvoicePdf::class, 'uploadedLogo');
        $uri = $pdf->invoke(app(\App\Services\InvoicePdf::class), $this->company);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }
}
