<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Events\PaymentRequested;
use App\Domain\Payments\Exceptions\PaymentAccountMissing;
use App\Domain\Payments\Exceptions\PaymentConflict;
use App\Domain\Payments\Exceptions\QrNeedsPaybill;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentAccount;
use App\Domain\Tenancy\Data\DeviceContext;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use VendaPOS\Core\Data\PaymentRequestData;
use VendaPOS\Core\Enums\PaymentChannel;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * Records a pending mobile-money payment, its callback route and its outbox
 * event in one transaction; the push itself runs in a job after commit.
 * Resending the same UUID with the same details returns the existing payment.
 * The payment keeps the account it was made with, so a later change of the
 * branch's account never moves a payment already in flight.
 */
final class RequestPayment
{
    /**
     * @return array{0: Payment, 1: bool} the payment, and whether it was created now
     *
     * @throws PaymentConflict
     * @throws PaymentAccountMissing
     * @throws QrNeedsPaybill
     */
    public function handle(PaymentRequestData $data, DeviceContext $context): array
    {
        try {
            return DB::transaction(fn () => $this->write($data, $context));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request committed the same UUID first.
            return [$this->existing($data, $context), false];
        }
    }

    /** @return array{0: Payment, 1: bool} */
    private function write(PaymentRequestData $data, DeviceContext $context): array
    {
        if (Payment::query()->whereKey($data->uuid)->lockForUpdate()->exists()) {
            return [$this->existing($data, $context), false];
        }

        $account = PaymentAccount::forBranch($data->provider, $context->branchId)
            ?? throw new PaymentAccountMissing("No {$data->provider->value} account for branch {$context->branchId}.");

        if ($data->channel === PaymentChannel::Qr && ! $account->isPaybill()) {
            throw new QrNeedsPaybill("Account {$account->id} is not a paybill.");
        }

        $token = Str::random(48);

        $payment = Payment::create([
            'id' => $data->uuid,
            'order_id' => $data->order_uuid,
            'branch_id' => $context->branchId,
            'device_id' => $context->deviceId,
            'payment_account_id' => $account->id,
            'provider' => $data->provider,
            'channel' => $data->channel,
            'phone' => $data->phone,
            'reference' => $data->channel === PaymentChannel::Qr ? self::newReference() : null,
            'amount_cents' => $data->amount()->cents,
            'currency' => $data->provider->currency(),
            'status' => PaymentStatus::Pending,
            'callback_token' => $token,
        ]);

        // Central table, written on the tenant connection so it commits with the payment.
        DB::table('public.payment_callback_routes')->insert([
            'token_hash' => hash('sha256', $token),
            'tenant_id' => $context->tenantId,
            'payment_id' => $payment->id,
            'provider' => $data->provider->value,
            'created_at' => now(),
        ]);

        $event = DomainEvent::record(
            type: 'payment.requested',
            tenantId: $context->tenantId,
            payload: [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'provider' => $payment->provider->value,
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
            ],
            aggregateId: $payment->id,
            branchId: $context->branchId,
            deviceId: $context->deviceId,
        );

        PaymentRequested::dispatch($event->event_id, $context->tenantId, $payment->id);

        return [$payment, true];
    }

    /**
     * The account number a QR payment is made to: short enough for a customer
     * to type on the paybill if they cannot scan, with no 0/O, 1/I/L or U to
     * misread. 32^8 references make a clash unlikely; the unique index catches one.
     */
    public static function newReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
        $reference = 'VP';

        for ($i = 0; $i < 8; $i++) {
            $reference .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $reference;
    }

    private function existing(PaymentRequestData $data, DeviceContext $context): Payment
    {
        $payment = Payment::query()->findOrFail($data->uuid);

        $same = $payment->device_id === $context->deviceId
            && $payment->provider === $data->provider
            && $payment->channel === $data->channel
            && $payment->phone === $data->phone
            && $payment->amount_cents === $data->amount()->cents
            && $payment->order_id === $data->order_uuid;

        if (! $same) {
            throw new PaymentConflict("Payment {$data->uuid} already exists with different details.");
        }

        return $payment;
    }
}
