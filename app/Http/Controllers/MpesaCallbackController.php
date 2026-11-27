<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Where Safaricom tells us how an STK push ended. There is no login on this request, so the secret uuid in the
 * URL (known only to us and Safaricom) identifies the push, and the body must name the matching CheckoutRequestID.
 * It always answers "accepted" for a known push so Safaricom does not keep retrying; problems go to the log.
 */
class MpesaCallbackController extends Controller
{
    public function __invoke(Request $request, string $uuid, MpesaService $mpesa): JsonResponse
    {
        $tx = MpesaTransaction::where('uuid', $uuid)->first();

        if (! $tx) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Unknown request'], 404);
        }

        try {
            $mpesa->handleCallback($tx, $request->json()->all());
        } catch (BusinessRuleException $e) {
            Log::warning("M-Pesa callback {$uuid} ignored: ".$e->getMessage());
        } catch (Throwable $e) {
            Log::error("M-Pesa callback {$uuid} failed: ".$e->getMessage());
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
