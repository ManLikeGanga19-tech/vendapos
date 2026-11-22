<?php

declare(strict_types=1);

use App\Domain\Payments\Models\MpesaCheck;
use Illuminate\Support\Facades\Lang;

it('words every kind of M-Pesa check for the owner, in both languages', function (string $locale) {
    foreach (MpesaCheck::KINDS as $kind) {
        foreach (["owner.mpesa_checks.kinds.{$kind}", "owner.mpesa_checks.details.{$kind}", "notifications.mpesa_checks.kinds.{$kind}"] as $key) {
            expect(Lang::has($key, $locale, false))->toBeTrue("{$key} is missing in {$locale}");
        }
    }
})->with(['en', 'sw']);
