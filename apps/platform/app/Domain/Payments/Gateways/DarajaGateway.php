<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentCredential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LogicException;
use VendaPOS\Core\Enums\MobileMoneyProvider;
use VendaPOS\Core\Enums\PaymentStatus;

/**
 * Safaricom Daraja M-Pesa Express (STK push) for Kenya, using the business's
 * own paybill or till. STK callbacks are not signed: the callback controller
 * checks the URL token and Safaricom's IPs, and this class checks that the
 * CheckoutRequestID matches (CLAUDE.md §4).
 */
final class DarajaGateway extends MobileMoneyGateway
{
    /** What `payments:credentials` asks for. transaction_type: CustomerPayBillOnline or CustomerBuyGoodsOnline. */
    public const array CREDENTIAL_FIELDS = ['consumer_key', 'consumer_secret', 'passkey', 'shortcode', 'party_b', 'transaction_type'];

    private const array BASE_URLS = [
        PaymentCredential::SANDBOX => 'https://sandbox.safaricom.co.ke',
        PaymentCredential::PRODUCTION => 'https://api.safaricom.co.ke',
    ];

    /** STK query error while the customer has not answered yet. */
    private const string STILL_PROCESSING = '500.001.1001';

    public function initiate(Payment $payment): GatewayResult
    {
        $credentials = $this->credentials();
        $timestamp = $this->timestamp();
        $phone = ltrim($payment->phone, '+');

        $response = $this->client($credentials)->post('/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $credentials['shortcode'],
            'Password' => $this->password($credentials, $timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => $credentials['transaction_type'],
            'Amount' => $payment->amount()->toWholeShillings(),
            'PartyA' => $phone,
            'PartyB' => $credentials['party_b'],
            'PhoneNumber' => $phone,
            'CallBackURL' => $payment->callbackUrl(),
            // Daraja allows 12 characters; shown to the customer on paybill.
            'AccountReference' => strtoupper(substr(str_replace('-', '', $payment->id), 0, 12)),
            'TransactionDesc' => 'Payment',
        ]);

        // A server error says nothing about whether the push went out: let the job fail the payment.
        if ($response->serverError()) {
            $response->throw();
        }

        $body = (array) $response->json();

        if ($response->successful() && (string) ($body['ResponseCode'] ?? '') === '0' && is_string($body['CheckoutRequestID'] ?? null)) {
            return GatewayResult::accepted($body['CheckoutRequestID']);
        }

        return GatewayResult::unsuccessful(
            PaymentStatus::Failed,
            (string) ($body['errorCode'] ?? $body['ResponseCode'] ?? $response->status()),
            (string) ($body['errorMessage'] ?? $body['ResponseDescription'] ?? 'The STK push was refused.'),
        );
    }

    public function query(Payment $payment): GatewayResult
    {
        if ($payment->provider_request_id === null) {
            return GatewayResult::pending();
        }

        $credentials = $this->credentials();
        $timestamp = $this->timestamp();

        $response = $this->client($credentials)->post('/mpesa/stkpushquery/v1/query', [
            'BusinessShortCode' => $credentials['shortcode'],
            'Password' => $this->password($credentials, $timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $payment->provider_request_id,
        ]);

        $body = (array) $response->json();

        if ((string) ($body['errorCode'] ?? '') === self::STILL_PROCESSING) {
            return GatewayResult::pending();
        }

        if ($response->failed() || ! isset($body['ResultCode'])) {
            $response->throw();

            return GatewayResult::pending();
        }

        // The query confirms the outcome but carries no receipt number; a later callback may add it.
        return $this->fromResultCode((string) $body['ResultCode'], (string) ($body['ResultDesc'] ?? ''), null, null);
    }

    public function parseCallback(array $body): ?array
    {
        $callback = $body['Body']['stkCallback'] ?? null;

        if (! is_array($callback) || ! is_string($callback['CheckoutRequestID'] ?? null) || ! isset($callback['ResultCode'])) {
            return null;
        }

        $metadata = [];

        foreach ((array) ($callback['CallbackMetadata']['Item'] ?? []) as $item) {
            if (is_array($item) && isset($item['Name'], $item['Value'])) {
                $metadata[(string) $item['Name']] = $item['Value'];
            }
        }

        return [
            'requestId' => $callback['CheckoutRequestID'],
            'result' => $this->fromResultCode(
                (string) $callback['ResultCode'],
                (string) ($callback['ResultDesc'] ?? ''),
                isset($metadata['MpesaReceiptNumber']) ? (string) $metadata['MpesaReceiptNumber'] : null,
                // Daraja reports shillings; convert without floats.
                isset($metadata['Amount']) && is_numeric($metadata['Amount']) ? (int) bcmul((string) $metadata['Amount'], '100', 0) : null,
            ),
        ];
    }

    private function fromResultCode(string $code, string $description, ?string $reference, ?int $amountCents): GatewayResult
    {
        return match ($code) {
            '0' => GatewayResult::succeeded($reference, $amountCents),
            '1032' => GatewayResult::unsuccessful(PaymentStatus::Cancelled, $code, $description ?: 'Cancelled by the customer.'),
            '1037' => GatewayResult::unsuccessful(PaymentStatus::TimedOut, $code, $description ?: 'The customer could not be reached.'),
            default => GatewayResult::unsuccessful(PaymentStatus::Failed, $code, $description ?: 'The payment failed.'),
        };
    }

    /** @return array<string, string> */
    private function credentials(): array
    {
        $credential = PaymentCredential::activeFor(MobileMoneyProvider::MpesaKe)
            ?? throw new LogicException('M-Pesa is not set up for this business.');

        return [...$credential->credentials, 'environment' => $credential->environment];
    }

    /** @param array<string, string> $credentials */
    private function client(array $credentials): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($credentials))
            ->withToken($this->accessToken($credentials))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);
    }

    /**
     * OAuth tokens last about an hour; cache them per consumer key, refreshing a minute early.
     *
     * @param  array<string, string>  $credentials
     */
    private function accessToken(array $credentials): string
    {
        $key = 'daraja:token:'.hash('sha256', $credentials['environment'].$credentials['consumer_key']);

        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached;
        }

        /** @var Response $response */
        $response = Http::withBasicAuth($credentials['consumer_key'], $credentials['consumer_secret'])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(10)
            ->get($this->baseUrl($credentials).'/oauth/v1/generate', ['grant_type' => 'client_credentials'])
            ->throw();

        $token = (string) $response->json('access_token');
        Cache::put($key, $token, max(60, (int) $response->json('expires_in', 3599) - 60));

        return $token;
    }

    /** @param array<string, string> $credentials */
    private function baseUrl(array $credentials): string
    {
        return self::BASE_URLS[$credentials['environment']] ?? throw new LogicException("Unknown Daraja environment {$credentials['environment']}.");
    }

    /** Daraja expects the shortcode's local time (EAT). */
    private function timestamp(): string
    {
        return now((string) config('vendapos.business_timezone'))->format('YmdHis');
    }

    /** @param array<string, string> $credentials */
    private function password(array $credentials, string $timestamp): string
    {
        return base64_encode($credentials['shortcode'].$credentials['passkey'].$timestamp);
    }
}
