<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAccount;
use VendaPOS\Core\Enums\PaymentStatus;

/** Stands in for every provider in tests, demos and local work without sandbox keys. */
final class FakeGateway extends MobileMoneyGateway
{
    public function initiate(Payment $payment): GatewayResult
    {
        return GatewayResult::accepted('fake-'.$payment->id);
    }

    /** A 1×1 white PNG: enough for the till to print and the tests to check. */
    public const string QR_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAAAAAA6fptVAAAACklEQVR4nGP4DwABAQEAWk1v8QAAAABJRU5ErkJggg==';

    public function generateQr(Payment $payment, string $merchantName): string
    {
        return self::QR_PNG;
    }

    public function query(Payment $payment): GatewayResult
    {
        return match (config('vendapos.payments.fake_outcome')) {
            'succeeded' => GatewayResult::succeeded('FAKE'.strtoupper(substr(md5($payment->id), 0, 6)), $payment->amount_cents),
            'failed' => GatewayResult::unsuccessful(PaymentStatus::Failed, 'fake_failure', 'Declined by the fake gateway.'),
            default => GatewayResult::pending(),
        };
    }

    /** Body: {"request_id": "...", "status": "succeeded|failed|cancelled|timed_out", "reference": "...", "amount_cents": 100} */
    public function parseCallback(array $body): ?array
    {
        $status = PaymentStatus::tryFrom((string) ($body['status'] ?? ''));

        if (! is_string($body['request_id'] ?? null) || $status === null) {
            return null;
        }

        return [
            'requestId' => $body['request_id'],
            'result' => $status === PaymentStatus::Succeeded
                ? GatewayResult::succeeded((string) ($body['reference'] ?? ''), isset($body['amount_cents']) ? (int) $body['amount_cents'] : null)
                : GatewayResult::unsuccessful($status, 'fake_'.$status->value, 'Reported by the fake gateway.'),
        ];
    }

    public function checkCredentials(array $credentials, string $environment): ?string
    {
        return null;
    }

    public function registerConfirmationUrls(PaymentAccount $account): ?string
    {
        return null;
    }
}
