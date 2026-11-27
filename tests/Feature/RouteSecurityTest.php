<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Walks every registered business route so a newly added page cannot ship
 * without authentication, company context and permission checks.
 */
class RouteSecurityTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    /** Routes a signed-in user with no permissions may legitimately open. */
    private const OPEN_TO_ANY_MEMBER = ['home', 'company.none', 'account', 'notifications.index', 'two-factor.setup', 'two-factor.recovery', 'two-factor.challenge'];

    /** @return list<LaravelRoute> */
    private function protectedGetRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $r) => in_array('GET', $r->methods(), true)
                && in_array('auth', $r->gatherMiddleware(), true))
            ->values()
            ->all();
    }

    private function url(LaravelRoute $route): string
    {
        $params = ['report' => 'sales', 'format' => 'pdf'];
        $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $params[$m[1]] ?? 1, $route->uri());

        return '/'.ltrim($uri, '/');
    }

    public function test_the_audit_actually_covers_the_application_routes(): void
    {
        $this->assertGreaterThan(20, count($this->protectedGetRoutes()));
    }

    public function test_every_protected_route_redirects_guests_to_login(): void
    {
        foreach ($this->protectedGetRoutes() as $route) {
            $this->get($this->url($route))->assertRedirect('/login');
        }
    }

    public function test_state_changing_routes_reject_guests(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/company/switch', ['company_id' => 1])->assertRedirect('/login');
    }

    public function test_a_member_without_permissions_cannot_open_any_business_page(): void
    {
        $this->seedAccess();
        $company = Company::factory()->create();
        $role = Role::create(['name' => 'Nothing', 'slug' => 'nothing']);
        $this->actingAs($this->userWithRole('nothing', $company));

        foreach ($this->protectedGetRoutes() as $route) {
            if (in_array($route->getName(), self::OPEN_TO_ANY_MEMBER, true)) {
                continue;
            }

            $status = $this->get($this->url($route))->getStatusCode();
            $this->assertContains($status, [403, 404], "{$route->getName()} ({$route->uri()}) answered {$status} for a user with no permissions");
        }

        $this->assertNotNull($role->id);
    }

    public function test_no_route_fails_with_a_server_error_for_a_fully_privileged_user(): void
    {
        $company = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $company));

        foreach ($this->protectedGetRoutes() as $route) {
            if ($route->getName() === 'reports.export') {
                continue; // covered by ReportPagesTest, returns a file
            }

            $status = $this->get($this->url($route))->getStatusCode();
            $this->assertLessThan(500, $status, "{$route->getName()} ({$route->uri()}) returned {$status}");
        }
    }

    public function test_the_admin_panel_is_closed_to_company_users(): void
    {
        $company = Company::factory()->create();
        $this->actingAs($this->userWithRole('company-administrator', $company));

        $this->assertContains($this->get('/admin')->getStatusCode(), [302, 403, 404]);
        $this->get('/admin')->assertDontSee('Dashboard');
    }
}
