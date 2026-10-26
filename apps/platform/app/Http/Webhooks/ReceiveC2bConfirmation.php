<?php

declare(strict_types=1);

namespace App\Http\Webhooks;

use App\Domain\Payments\Jobs\ProcessC2bConfirmation;
use App\Domain\Payments\Models\C2bRoute;
use App\Infrastructure\Webhooks\WebhookReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * POST /webhooks/c2b/{token}/confirm — Safaricom's notice of money paid
 * straight into an account. Like STK callbacks it is not signed (CLAUDE.md §4):
 * the URL token and Safaricom's IPs are checked, the raw body is logged, a job
 * does the rest, and every caller gets the same reply.
 */
final class ReceiveC2bConfirmation
{
    /** WebhookReceipt::provider for C2B confirmations. */
    public const string PROVIDER = 'mpesa_c2b';

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $route = C2bRoute::forToken($token);

        $receipt = WebhookReceipt::query()->create([
            'provider' => self::PROVIDER,
            'token_hash' => hash('sha256', $token),
            'tenant_id' => $route?->tenant_id,
            'ip' => (string) $request->ip(),
            'body' => $request->getContent(),
            'status' => WebhookReceipt::RECEIVED,
            'received_at' => now(),
        ]);

        $problem = match (true) {
            $route === null => 'unknown_token',
            ! $this->fromSafaricom((string) $request->ip()) => 'ip_not_allowed',
            default => null,
        };

        if ($problem !== null) {
            Log::channel('security')->warning("payments.c2b_{$problem}", ['ip' => $request->ip(), 'receipt_id' => $receipt->id]);
            $receipt->reject($problem);
        } else {
            ProcessC2bConfirmation::dispatch($route->tenant_id, $route->payment_account_id, $receipt->id);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    }

    /**
     * POST /webhooks/c2b/{token}/validate — only called when Safaricom has
     * external validation switched on for the shortcode. Every payment is
     * welcome, so it always accepts and records nothing.
     */
    public function validate(): JsonResponse
    {
        return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
    }

    private function fromSafaricom(string $ip): bool
    {
        /** @var list<string> $allowed */
        $allowed = config('vendapos.payments.callback_ips.mpesa_ke', []);

        return ! config('vendapos.payments.enforce_callback_ips') || IpUtils::checkIp($ip, $allowed);
    }
}
