<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Models\MpesaCheck;

/**
 * Two sales paid the same ticket. The payment hold should make this
 * impossible, so it is never silent: the owner sees it with the money to
 * check and refunds one. Both sales stand: the money really was taken.
 */
final class FlagTicketPaidTwice
{
    /** @param  list<string>  $orderIds */
    public function handle(string $ticketId, string $branchId, array $orderIds): void
    {
        MpesaCheck::open(MpesaCheck::TICKET_PAID_TWICE, $ticketId, [
            'branch_id' => $branchId,
            'order_id' => $orderIds[array_key_last($orderIds)] ?? null,
            'details' => ['ticket_id' => $ticketId, 'order_ids' => $orderIds],
        ]);
    }
}
