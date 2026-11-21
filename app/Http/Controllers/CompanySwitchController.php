<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveCurrentCompany;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanySwitchController extends Controller
{
    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', 'integer']]);

        // Never trust the submitted id: it must be one of the user's own active companies.
        $company = $request->user()->accessibleCompanies()->find($data['company_id']);
        abort_if($company === null, 403, 'You do not have access to that company.');

        $request->session()->put(ResolveCurrentCompany::SESSION_KEY, $company->getKey());
        $logger->log('company.switched', $company, companyId: $company->getKey());

        return redirect()->route('home')->with('status', "Now managing {$company->name}.");
    }

    public function none(Request $request): View
    {
        return view('company.none');
    }
}
