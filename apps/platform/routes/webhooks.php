<?php

declare(strict_types=1);

use App\Http\Webhooks\ReceivePaymentCallback;
use Illuminate\Support\Facades\Route;
use VendaPOS\Core\Enums\MobileMoneyProvider;

/*
| Provider callbacks: central (no tenant subdomain, no device token), stateless,
| size-limited. The URL token routes each callback to one tenant's payment.
*/
Route::post('webhooks/payments/{provider}/{token}', ReceivePaymentCallback::class)
    ->middleware(['body.limit', 'throttle:payment-callbacks'])
    ->whereIn('provider', array_column(MobileMoneyProvider::cases(), 'value'))
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('webhooks.payments');
