<?php

namespace App\Http\Controllers;

use App\Models\CompanyAsset;
use Illuminate\Http\Response;

class CompanyAssetController extends Controller
{
    /** Serves the signed-in company's own logo or background. Another company's image is never reachable (global company scope). */
    public function show(string $kind): Response
    {
        abort_unless(in_array($kind, [CompanyAsset::LOGO, CompanyAsset::BACKGROUND], true), 404);

        $asset = CompanyAsset::where('kind', $kind)->firstOrFail();

        return response($asset->bytes(), 200, [
            'Content-Type' => $asset->mime,
            'Cache-Control' => 'private, max-age=86400', // the ?v= in the link changes whenever the image does
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
