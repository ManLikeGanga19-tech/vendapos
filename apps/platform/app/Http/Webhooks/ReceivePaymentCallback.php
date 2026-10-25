<?php

declare(strict_types=1);

namespace App\Http\Webhooks;

use App\Domain\Payments\Jobs\ProcessPaymentCallback;
use App\Domain\Payments\Models\PaymentCallbackRoute;
use App\Infrastructure\Webhooks\WebhookReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use VendaPOS\Core\Enums\MobileMoneyProvider;

/**
 * POST /webhooks/payments/{token}. Logs the raw body, checks the URL token
 * and the source IPs of the provider it belongs to, then hands off to a job and
 * answers at once. Every caller gets the same reply, so a probe learns nothing.
 */
final class ReceivePaymentCallback
{
    /** Logged as the provider of a callback whose token matches no payment. */
    private const string UNKNOWN_PROVIDER = 'unknown';

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $route = PaymentCallbackRoute::forToken($token);
        $provider = MobileMoneyProvider::tryFrom((string) $route?->provider);

        $receipt = WebhookReceipt::query()->create([
            'provider' => $provider->value ?? self::UNKNOWN_PROVIDER,
            'token_hash' => hash('sha256', $token),
            'tenant_id' => $route?->tenant_id,
            'payment_id' => $route?->payment_id,
            'ip' => (string) $request->ip(),
            'body' => $request->getContent(),
            'status' => WebhookReceipt::RECEIVED,
            'received_at' => now(),
        ]);

        $problem = match (true) {
            $route === null, $provider === null => 'unknown_token',
            ! $this->fromProvider($provider, (string) $request->ip()) => 'ip_not_allowed',
            default => null,
        };

        if ($problem !== null) {
            Log::channel('security')->warning("payments.callback_{$problem}", ['provider' => $provider?->value, 'ip' => $request->ip(), 'receipt_id' => $receipt->id]);
            $receipt->reject($problem);
        } else {
            ProcessPaymentCallback::dispatch($route->tenant_id, $receipt->id);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function fromProvider(MobileMoneyProvider $provider, string $ip): bool
    {
        /** @var list<string> $allowed */
        $allowed = config("vendapos.payments.callback_ips.{$provider->value}", []);

        return ! config('vendapos.payments.enforce_callback_ips') || IpUtils::checkIp($ip, $allowed);
    }
}
