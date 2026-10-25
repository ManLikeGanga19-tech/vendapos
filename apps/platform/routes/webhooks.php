<?php

declare(strict_types=1);

use App\Http\Webhooks\ReceivePaymentCallback;
use Illuminate\Support\Facades\Route;

/*
| Provider callbacks: central (no tenant subdomain, no device token), stateless,
| size-limited. The URL token routes each callback to one tenant's payment and
| names its provider. Safaricom refuses callback URLs containing words such as
| "mpesa" or "safaricom", so the path carries no provider name.
*/
Route::post('webhooks/payments/{token}', ReceivePaymentCallback::class)
    ->middleware(['body.limit', 'throttle:payment-callbacks'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('webhooks.payments');
