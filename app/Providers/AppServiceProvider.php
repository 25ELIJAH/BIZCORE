<?php

namespace App\Providers;

use App\Http\Middleware\ResolveCurrentCompany;
use App\Models\User;
use App\Support\Access\PermissionCatalog;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentCompany::class);
    }

    public function boot(): void
    {
        // Livewire's /livewire/update requests bypass route middleware unless it is declared persistent.
        // Without this, component actions would run with no company context.
        Livewire::addPersistentMiddleware([ResolveCurrentCompany::class]);

        // Make the active company available to the layout and every page that extends it.
        View::composer(['layouts.app', 'home'], fn ($view) => $view->with('currentCompany', app(CurrentCompany::class)->get()));

        // Every permission slug is a gate, so `can:sales.view`, @can and $this->authorize() all use one check
        // against the user's role in the *current* company.
        foreach (PermissionCatalog::slugs() as $slug) {
            Gate::define($slug, fn (User $user) => $user->hasPermissionIn(app(CurrentCompany::class)->id(), $slug));
        }
    }
}
