<?php

declare(strict_types=1);

namespace App\Tickets;

use App\Api\Data\BillQr;
use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\Exceptions\TokenStillValid;
use App\Api\VendaApi;
use App\Models\Ticket;
use App\Security\KeyringUnavailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Asks the server for the bill's scan-to-pay QR (Daraja Dynamic QR), for
 * the ticket's total in whole shillings; any cents are paid in cash. Every
 * bill gets a fresh one: if the customer pays an older bill's QR the money
 * is the owner's to look at (Daniel, 2026-10-30). Returns null when there is
 * none to print: offline, a buy-goods till, or nothing to pay by M-Pesa.
 */
final class PrepareBillQr
{
    /** Polls for the image every half second, up to this long, before printing without it. */
    public const int WAIT_MILLISECONDS = 8_000;

    private const int POLL_MILLISECONDS = 500;

    public function __construct(private readonly VendaApi $api) {}

    public function handle(Ticket $ticket): ?BillQr
    {
        $amount = intdiv($ticket->totals()->total->cents, 100) * 100;

        if ($amount < 100) {
            return null;
        }

        // The sale that pays the ticket: the payment names it, and the pay screen uses it.
        if ($ticket->sale_id === null) {
            $ticket->update(['sale_id' => (string) Str::uuid7()]);
        }

        $paymentId = (string) Str::uuid7();

        try {
            $payment = $this->api->requestQrPayment($paymentId, (string) $ticket->sale_id, $amount);
            $ticket->update(['qr_payment_id' => $paymentId]);

            for ($waited = 0; $payment->qr?->png === null && $payment->failureCode === null && $waited < self::WAIT_MILLISECONDS; $waited += self::POLL_MILLISECONDS) {
                Sleep::for(self::POLL_MILLISECONDS)->milliseconds();
                $payment = $this->api->payment($paymentId);
            }
        } catch (ApiRejected $e) {
            // Usually a buy-goods till (qr_needs_paybill): the bill prints as before.
            Log::info('till.bill_qr_refused', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);

            return null;
        } catch (ApiUnavailable|TillNotAuthorised|TokenStillValid|ApiNotConfigured|KeyringUnavailable $e) {
            Log::info('till.bill_qr_unavailable', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);

            return null;
        }

        return $payment->qr;
    }
}
