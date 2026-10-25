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
 * POST /webhooks/payments/{provider}/{token}. Logs the raw body, checks the
 * URL token and the provider's source IPs, then hands off to a job and
 * answers at once. Every caller gets the same reply, so a probe learns nothing.
 */
final class ReceivePaymentCallback
{
    public function __invoke(Request $request, string $provider, string $token): JsonResponse
    {
        $provider = MobileMoneyProvider::from($provider);
        $route = PaymentCallbackRoute::forToken($provider->value, $token);

        $receipt = WebhookReceipt::query()->create([
            'provider' => $provider->value,
            'token_hash' => hash('sha256', $token),
            'tenant_id' => $route?->tenant_id,
            'payment_id' => $route?->payment_id,
            'ip' => (string) $request->ip(),
            'body' => $request->getContent(),
            'status' => WebhookReceipt::RECEIVED,
            'received_at' => now(),
        ]);

        $problem = match (true) {
            $route === null => 'unknown_token',
            ! $this->fromProvider($provider, (string) $request->ip()) => 'ip_not_allowed',
            default => null,
        };

        if ($problem !== null) {
            Log::channel('security')->warning("payments.callback_{$problem}", ['provider' => $provider->value, 'ip' => $request->ip(), 'receipt_id' => $receipt->id]);
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
