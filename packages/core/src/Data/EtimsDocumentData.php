<?php

declare(strict_types=1);

namespace VendaPOS\Core\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use VendaPOS\Core\Enums\EtimsDocumentType;
use VendaPOS\Core\Enums\PaymentType;

/**
 * Everything an eTIMS client needs to report one sale or credit note. It holds
 * VendaPOS's own values only; mapping them to KRA's OSCU/VSCU fields and codes
 * is the client's job, once the spec is confirmed.
 */
final class EtimsDocumentData extends Data
{
    /**
     * @param  DataCollection<int, EtimsLineData>  $lines
     * @param  array<string, mixed>|null  $original_receipt  for a credit note, the receipt KRA gave the sale
     */
    public function __construct(
        public EtimsDocumentType $document_type,
        public string $order_id,
        public string $branch_id,
        // KRA branch ID (bhfId); null until the branch is registered.
        public ?string $etims_branch_id,
        public string $device_id,
        // Per-device and strictly increasing: the document's invoice number on our side.
        public int $sequence_number,
        public string $placed_at,
        public PaymentType $payment_type,
        public int $subtotal_cents,
        public int $vat_cents,
        public int $total_cents,
        #[DataCollectionOf(EtimsLineData::class)]
        public DataCollection $lines,
        public ?array $original_receipt = null,
    ) {}
}
