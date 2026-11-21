<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/** Throwaway tenant model: real tenant models arrive with the business modules. */
class IsolationProbe extends Model
{
    use BelongsToCompany;

    protected $table = 'isolation_probes';

    protected $guarded = [];
}

class CompanyIsolationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private Company $a;

    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('isolation_probes', function ($table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('label');
            $table->timestamps();
        });

        $this->a = Company::factory()->create();
        $this->b = Company::factory()->create();
        $tenancy = app(CurrentCompany::class);
        $tenancy->runAs($this->a, fn () => IsolationProbe::create(['label' => 'a-record']));
        $tenancy->runAs($this->b, fn () => IsolationProbe::create(['label' => 'b-record']));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('isolation_probes');
        parent::tearDown();
    }

    public function test_queries_only_return_the_current_companys_rows(): void
    {
        app(CurrentCompany::class)->set($this->a);

        $this->assertSame(['a-record'], IsolationProbe::pluck('label')->all());
    }

    public function test_another_companys_record_id_cannot_be_loaded(): void
    {
        $bRecord = IsolationProbe::withoutGlobalScopes()->where('label', 'b-record')->firstOrFail();
        app(CurrentCompany::class)->set($this->a);

        $this->assertNull(IsolationProbe::find($bRecord->id));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        IsolationProbe::findOrFail($bRecord->id);
    }

    public function test_queries_fail_closed_without_a_company_context(): void
    {
        $this->assertSame(0, IsolationProbe::count());
    }

    public function test_company_id_is_assigned_from_context_on_create(): void
    {
        app(CurrentCompany::class)->set($this->b);

        $this->assertSame($this->b->id, IsolationProbe::create(['label' => 'new'])->company_id);
    }

    public function test_creating_a_record_for_another_company_is_refused(): void
    {
        app(CurrentCompany::class)->set($this->a);

        $this->expectException(LogicException::class);
        IsolationProbe::create(['label' => 'sneaky', 'company_id' => $this->b->id]);
    }

    public function test_creating_without_a_company_context_is_refused(): void
    {
        $this->expectException(LogicException::class);
        IsolationProbe::create(['label' => 'orphan']);
    }

    public function test_route_model_binding_returns_404_for_another_companys_record(): void
    {
        Route::middleware(['web', 'auth', 'company'])->get('/probe/{probe}', fn (IsolationProbe $probe) => $probe->label);
        Route::bind('probe', fn ($id) => IsolationProbe::findOrFail($id));

        $user = $this->userWithRole('manager', $this->a);
        $bRecord = IsolationProbe::withoutGlobalScopes()->where('label', 'b-record')->firstOrFail();
        $aRecord = IsolationProbe::withoutGlobalScopes()->where('label', 'a-record')->firstOrFail();

        $this->actingAs($user)->get("/probe/{$aRecord->id}")->assertOk()->assertSee('a-record');
        $this->get("/probe/{$bRecord->id}")->assertNotFound();
    }

    public function test_the_tenant_context_does_not_leak_after_run_as(): void
    {
        $tenancy = app(CurrentCompany::class);
        $tenancy->set($this->a);
        $tenancy->runAs($this->b, fn () => $this->assertSame($this->b->id, $tenancy->id()));

        $this->assertSame($this->a->id, $tenancy->id());
    }
}
