<?php

declare(strict_types=1);

namespace App\Domain\Etims\Actions;

use App\Domain\Catalogue\Actions\FindEtimsItemCodes;
use App\Domain\Etims\Models\EtimsSubmission;
use App\Domain\Tenancy\Actions\FindEtimsBranchId;
use Spatie\LaravelData\DataCollection;
use VendaPOS\Core\Data\EtimsDocumentData;
use VendaPOS\Core\Data\EtimsLineData;
use VendaPOS\Core\Enums\PaymentType;
use VendaPOS\Core\Enums\VatCategory;

/**
 * Turns a submission's order snapshot into the document a client sends. The
 * KRA branch ID and item codes are read now, not when the order synced, so a
 * branch registered after a failed attempt is picked up on retry.
 */
final class BuildEtimsDocument
{
    public function __construct(
        private readonly FindEtimsBranchId $findEtimsBranchId,
        private readonly FindEtimsItemCodes $findEtimsItemCodes,
    ) {}

    /** @param array<string, mixed>|null $originalReceipt */
    public function handle(EtimsSubmission $submission, ?array $originalReceipt = null): EtimsDocumentData
    {
        $order = $submission->order_snapshot;

        /** @var list<array<string, mixed>> $lines */
        $lines = $order['lines'];
        $codes = $this->findEtimsItemCodes->handle(array_map(fn (array $line) => (string) $line['item_id'], $lines));

        return new EtimsDocumentData(
            document_type: $submission->document_type,
            order_id: $submission->order_id,
            branch_id: $submission->branch_id,
            etims_branch_id: $this->findEtimsBranchId->handle($submission->branch_id),
            device_id: (string) $order['device_id'],
            sequence_number: (int) $order['sequence_number'],
            placed_at: (string) $order['placed_at'],
            payment_type: PaymentType::from((string) $order['payment_type']),
            subtotal_cents: (int) $order['subtotal_cents'],
            vat_cents: (int) $order['vat_cents'],
            total_cents: (int) $order['total_cents'],
            lines: new DataCollection(EtimsLineData::class, array_map(fn (array $line) => new EtimsLineData(
                item_id: (string) $line['item_id'],
                name: (string) $line['name'],
                quantity: (int) $line['quantity'],
                unit_price_cents: (int) $line['unit_price_cents'],
                vat_category: VatCategory::from((string) $line['vat_category']),
                line_total_cents: (int) $line['line_total_cents'],
                vat_cents: (int) $line['vat_cents'],
                item_codes: $codes[(string) $line['item_id']] ?? null,
            ), $lines)),
            original_receipt: $originalReceipt,
        );
    }
}
