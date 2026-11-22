<?php

use App\Http\Middleware\ResolveCurrentCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Vercel's proxy the request arrives as plain HTTP; trust X-Forwarded-* so URLs are generated as https.
        $middleware->trustProxies(at: '*');
        $middleware->alias(['company' => ResolveCurrentCompany::class, 'two-factor' => \App\Http\Middleware\RequireTwoFactor::class]);
        // The tenant context must exist before route-model binding runs, otherwise bound
        // records are looked up with no company (fail-closed 404) or the wrong one.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveCurrentCompany::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
