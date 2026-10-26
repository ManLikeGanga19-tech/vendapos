<?php

declare(strict_types=1);

namespace App\Domain\Payments\Jobs;

use App\Domain\Payments\Events\MpesaConfirmed;
use App\Domain\Payments\Models\MpesaConfirmation;
use App\Domain\Payments\Models\PaymentAccount;
use App\Infrastructure\Queue\LiveTenants;
use App\Infrastructure\Webhooks\WebhookReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Stores one C2B confirmation in the account's tenant, once per M-Pesa code:
 * Safaricom may send the same confirmation again, which changes nothing.
 */
final class ProcessC2bConfirmation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $accountId,
        public readonly int $receiptId,
    ) {
        $this->onQueue('payments');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(): void
    {
        if (! LiveTenants::has($this->tenantId)) {
            return;
        }

        $previous = tenant();
        tenancy()->initialize($this->tenantId);

        try {
            $receipt = WebhookReceipt::query()->findOrFail($this->receiptId);

            if ($receipt->status !== WebhookReceipt::RECEIVED || $receipt->tenant_id !== $this->tenantId) {
                return;
            }

            $account = PaymentAccount::query()->find($this->accountId);
            $parsed = self::parse((array) json_decode($receipt->body, true));

            if ($account === null || $parsed === null) {
                $receipt->reject('unparseable');

                return;
            }

            // A till's money may be confirmed under its store number or its till number.
            if (! in_array($parsed['business_short_code'], [$account->credentials['shortcode'] ?? null, $account->credentials['party_b'] ?? null], true)) {
                $receipt->reject('shortcode_mismatch');

                return;
            }

            try {
                $confirmation = MpesaConfirmation::query()->create([
                    ...$parsed,
                    'payment_account_id' => $account->id,
                    'webhook_receipt_id' => $receipt->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                $receipt->markProcessed('duplicate');

                return;
            }

            $receipt->markProcessed();
            MpesaConfirmed::dispatch($this->tenantId, $confirmation->id);
        } finally {
            $previous === null ? tenancy()->end() : tenancy()->initialize($previous);
        }
    }

    /**
     * Daraja's C2B confirmation body, read without trusting its types. The
     * phone number arrives masked and is kept that way.
     *
     * @param  array<mixed>  $body
     * @return array{trans_id: string, trans_type: string|null, amount_cents: int, business_short_code: string, bill_ref: string|null, msisdn: string|null, payer_name: string|null, paid_at: CarbonImmutable}|null
     */
    public static function parse(array $body): ?array
    {
        $transId = $body['TransID'] ?? null;
        $amount = is_string($body['TransAmount'] ?? null) || is_int($body['TransAmount'] ?? null) ? (string) $body['TransAmount'] : '';
        $shortCode = $body['BusinessShortCode'] ?? null;
        $time = (string) ($body['TransTime'] ?? '');

        // Shillings with at most two decimals: never a float, never an exponent.
        if (! is_string($transId) || preg_match('/^[A-Z0-9]{8,20}$/', $transId) !== 1
            || preg_match('/^\d+(\.\d{1,2})?$/', $amount) !== 1 || ! is_numeric($amount) || bccomp($amount, '0', 2) <= 0
            || (! is_string($shortCode) && ! is_int($shortCode))
            || preg_match('/^\d{14}$/', $time) !== 1) {
            return null;
        }

        // Safaricom reports Nairobi time.
        $paidAt = CarbonImmutable::createFromFormat('YmdHis', $time, (string) config('vendapos.business_timezone'));

        if (! $paidAt instanceof CarbonImmutable) {
            return null;
        }

        $name = trim(implode(' ', array_filter([$body['FirstName'] ?? null, $body['MiddleName'] ?? null, $body['LastName'] ?? null], fn (mixed $part) => is_string($part) && trim($part) !== '')));

        return [
            'trans_id' => $transId,
            'trans_type' => is_string($body['TransactionType'] ?? null) ? substr($body['TransactionType'], 0, 32) : null,
            // Shillings as text; converted without floats.
            'amount_cents' => (int) bcmul($amount, '100', 0),
            'business_short_code' => (string) $shortCode,
            'bill_ref' => is_string($body['BillRefNumber'] ?? null) && $body['BillRefNumber'] !== '' ? substr($body['BillRefNumber'], 0, 32) : null,
            'msisdn' => is_string($body['MSISDN'] ?? null) ? substr($body['MSISDN'], 0, 32) : null,
            'payer_name' => $name !== '' ? substr($name, 0, 120) : null,
            'paid_at' => $paidAt->utc(),
        ];
    }
}
