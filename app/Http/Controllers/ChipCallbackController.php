<?php

namespace App\Http\Controllers;

use App\Services\Billing\BillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** CHIP success_callback: server-to-server, signed with X-Signature. */
class ChipCallbackController
{
    public function __invoke(Request $request, BillingService $billing): Response
    {
        $ok = $billing->handleCallback($request->getContent(), $request->header('X-Signature'));

        return response($ok ? 'OK' : 'Invalid signature', $ok ? 200 : 400);
    }
}
