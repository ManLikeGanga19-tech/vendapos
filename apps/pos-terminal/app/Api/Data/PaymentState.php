<?php

declare(strict_types=1);

namespace App\Api\Data;

/** A mobile-money payment as the server reports it (GET /api/v1/payments/{uuid}). */
final readonly class PaymentState
{
    public const string PENDING = 'pending';

    public const string SUCCEEDED = 'succeeded';

    public function __construct(
        public string $uuid,
        public string $status,
        public int $amountCents,
        public ?string $providerReference,
        public ?string $failureReason,
        // Set while pending too, e.g. qr_failed: Safaricom could not make the QR.
        public ?string $failureCode = null,
        // A scan-to-pay payment's QR for the bill; null for a push.
        public ?BillQr $qr = null,
    ) {}

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function succeeded(): bool
    {
        return $this->status === self::SUCCEEDED;
    }
}
