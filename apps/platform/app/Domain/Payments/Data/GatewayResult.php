<?php

declare(strict_types=1);

namespace App\Domain\Payments\Data;

use VendaPOS\Core\Enums\PaymentStatus;

/**
 * What a provider said about a payment, from a push, a status query or a
 * callback. Pending means "no final answer yet" (with the provider's request
 * ID once the push was accepted).
 */
final readonly class GatewayResult
{
    private function __construct(
        public PaymentStatus $status,
        public ?string $requestId = null,
        public ?string $reference = null,
        public ?string $failureCode = null,
        public ?string $failureReason = null,
        // The amount the provider says was paid, when it reports one.
        public ?int $amountCents = null,
    ) {}

    /** The provider took the push and will report back. */
    public static function accepted(string $requestId): self
    {
        return new self(PaymentStatus::Pending, requestId: $requestId);
    }

    public static function pending(): self
    {
        return new self(PaymentStatus::Pending);
    }

    /** $reference is null when the provider confirms without one (e.g. a Daraja status query). */
    public static function succeeded(?string $reference, ?int $amountCents = null): self
    {
        return new self(PaymentStatus::Succeeded, reference: $reference, amountCents: $amountCents);
    }

    public static function unsuccessful(PaymentStatus $status, string $code, string $reason): self
    {
        return new self($status, failureCode: $code, failureReason: $reason);
    }
}
