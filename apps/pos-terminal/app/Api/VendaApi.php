<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Data\BillQr;
use App\Api\Data\BranchInfo;
use App\Api\Data\CatalogueSnapshot;
use App\Api\Data\DeviceStatus;
use App\Api\Data\IssuedToken;
use App\Api\Data\PairingResult;
use App\Api\Data\PaymentState;
use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiRejected;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\Exceptions\TokenStillValid;
use App\Till\Till;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;

/**
 * The till's side of /api/v1. Every failure becomes one of: ApiUnavailable
 * (try later), TillNotAuthorised (token refused), ApiRejected (422, message
 * safe to show) or TokenStillValid (409 on renewal).
 */
final class VendaApi
{
    public const string DEVICE_HEADER = 'X-Device-ID';

    public function __construct(private readonly Till $till) {}

    public function pair(string $code): PairingResult
    {
        $json = $this->send(fn () => $this->request()->post('device/pair', ['code' => $code]));

        return new PairingResult(
            deviceId: self::string($json, 'device.id'),
            deviceName: self::string($json, 'device.name'),
            businessName: self::string($json, 'business.name'),
            branch: self::branch($json),
            token: new IssuedToken(self::string($json, 'token'), self::date($json, 'token_expires_at')),
            tillCode: self::nullableString($json, 'device.till_code'),
        );
    }

    public function device(): DeviceStatus
    {
        $json = $this->send(fn () => $this->authorised()->get('device'));

        return new DeviceStatus(
            deviceName: self::string($json, 'device.name'),
            branch: self::branch($json),
            tokenExpiresAt: self::date($json, 'token_expires_at'),
            accountStatus: self::string($json, 'account_status'),
            realtime: self::realtime($json),
            tillCode: self::nullableString($json, 'device.till_code'),
        );
    }

    /**
     * Sign this till's private-channel subscription. The device token stays
     * here in PHP; the window only ever sees the signature.
     *
     * @return array<mixed> the server's answer, as Pusher clients expect it ({"auth": "..."})
     */
    public function authoriseChannel(string $socketId, string $channelName): array
    {
        // /api/broadcasting/auth sits beside /api/v1, not under it.
        return $this->send(fn () => $this->authorised('/api')->post('broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => $channelName,
        ]));
    }

    /** Swap a still-valid token for a fresh one. */
    public function refreshToken(): IssuedToken
    {
        $json = $this->send(fn () => $this->authorised()->post('device/token'));

        return new IssuedToken(self::string($json, 'token'), self::date($json, 'expires_at'));
    }

    /** Swap a token that expired within the grace window for a fresh one. */
    public function renewToken(): IssuedToken
    {
        $json = $this->send(fn () => $this->authorised()->post('device/token/renew'));

        return new IssuedToken(self::string($json, 'token'), self::date($json, 'expires_at'));
    }

    public function catalogue(?CarbonImmutable $updatedSince = null): CatalogueSnapshot
    {
        $query = $updatedSince === null ? [] : ['updated_since' => $updatedSince->toIso8601String()];
        $json = $this->send(fn () => $this->authorised()->get('catalogue', $query));

        return new CatalogueSnapshot(
            syncedAt: self::date($json, 'synced_at') ?? throw new ApiUnavailable('Catalogue answer had no synced_at.'),
            full: (bool) data_get($json, 'full'),
            categories: array_map(fn (array $category): array => [
                'id' => self::string($category, 'id'),
                'name' => self::string($category, 'name'),
                'sort_order' => (int) data_get($category, 'sort_order'),
            ], self::list($json, 'categories')),
            items: array_map(fn (array $item): array => [
                'id' => self::string($item, 'id'),
                'category_id' => self::nullableString($item, 'category_id'),
                'type' => self::string($item, 'type'),
                'name' => self::string($item, 'name'),
                'sku' => self::nullableString($item, 'sku'),
                'barcode' => self::nullableString($item, 'barcode'),
                'price_cents' => (int) data_get($item, 'price_cents'),
                'vat_category' => self::string($item, 'vat_category'),
                'is_active' => (bool) data_get($item, 'is_active'),
            ], self::list($json, 'items')),
        );
    }

    /**
     * This till's branch's areas and tables in use (table service); empty elsewhere.
     *
     * @return list<array{id: string, name: string, sort_order: int, tables: list<array{id: string, name: string, seats: int, sort_order: int}>}>
     */
    public function floor(): array
    {
        $json = $this->send(fn () => $this->authorised()->get('floor'));

        return array_map(fn (array $area): array => [
            'id' => self::string($area, 'id'),
            'name' => self::string($area, 'name'),
            'sort_order' => (int) data_get($area, 'sort_order'),
            'tables' => array_map(fn (array $table): array => [
                'id' => self::string($table, 'id'),
                'name' => self::string($table, 'name'),
                'seats' => (int) data_get($table, 'seats'),
                'sort_order' => (int) data_get($table, 'sort_order'),
            ], self::list($area, 'tables')),
        ], self::list($json, 'areas'));
    }

    /**
     * Everyone who can sign in on this till's branch, with PIN hashes.
     *
     * @return list<array{id: string, name: string, role: string, permissions: list<string>, pin_hash: string, pin_length: int}>
     */
    public function staff(): array
    {
        $json = $this->send(fn () => $this->authorised()->get('staff'));

        return array_map(fn (array $staff): array => [
            'id' => self::string($staff, 'id'),
            'name' => self::string($staff, 'name'),
            'role' => self::string($staff, 'role'),
            'permissions' => array_values(array_map(strval(...), (array) data_get($staff, 'permissions', []))),
            'pin_hash' => self::string($staff, 'pin_hash'),
            'pin_length' => (int) data_get($staff, 'pin_length'),
        ], self::list($json, 'staff'));
    }

    /**
     * Change a staff member's PIN, proving the current one. Answers with their
     * new staff-list entry, PIN hash included.
     *
     * @return array{id: string, name: string, role: string, permissions: list<string>, pin_hash: string, pin_length: int}
     */
    public function changePin(string $staffId, #[SensitiveParameter] string $currentPin, #[SensitiveParameter] string $newPin): array
    {
        $json = $this->send(fn () => $this->authorised()->post("staff/{$staffId}/pin", [
            'current_pin' => $currentPin,
            'new_pin' => $newPin,
        ]));

        $staff = data_get($json, 'staff');

        if (! is_array($staff)) {
            throw new ApiUnavailable('The server sent no staff entry.');
        }

        return [
            'id' => self::string($staff, 'id'),
            'name' => self::string($staff, 'name'),
            'role' => self::string($staff, 'role'),
            'permissions' => array_values(array_map(strval(...), (array) data_get($staff, 'permissions', []))),
            'pin_hash' => self::string($staff, 'pin_hash'),
            'pin_length' => (int) data_get($staff, 'pin_length'),
        ];
    }

    /**
     * Send up to 50 sales. One answer per sale, in the order sent; one bad
     * sale never stops the others.
     *
     * @param  list<array<string, mixed>>  $orders
     * @return list<array{uuid: string|null, status: string, reason: string|null, errors: array<mixed>}>
     */
    public function syncOrders(array $orders): array
    {
        $json = $this->send(fn () => $this->authorised()->post('orders/sync', ['orders' => $orders]));

        return array_map(fn (array $result): array => [
            'uuid' => self::nullableString($result, 'uuid'),
            'status' => self::string($result, 'status'),
            'reason' => self::nullableString($result, 'reason'),
            'errors' => (array) data_get($result, 'errors', []),
        ], self::list($json, 'results'));
    }

    /**
     * @param  list<array<string, string>>  $reprints  up to 50, as ReceiptReprint::toSyncPayload()
     * @return list<array{uuid: string|null, status: string, reason: string|null}>
     */
    public function syncReprints(array $reprints): array
    {
        $json = $this->send(fn () => $this->authorised()->post('receipts/reprints/sync', ['reprints' => $reprints]));

        return array_map(fn (array $result): array => [
            'uuid' => self::nullableString($result, 'uuid'),
            'status' => self::string($result, 'status'),
            'reason' => self::nullableString($result, 'reason'),
        ], self::list($json, 'results'));
    }

    /**
     * This branch's tickets not yet paid, in full (table service).
     *
     * @return list<array{id: string, status: string, table_id: string|null, table_name: string|null, label: string|null, staff_id: string, opened_at: string, bill_requested_at: string|null, lines: list<array<string, mixed>>}>
     */
    public function tickets(): array
    {
        $json = $this->send(fn () => $this->authorised()->get('tickets'));

        return array_map(fn (array $ticket): array => [
            'id' => self::string($ticket, 'id'),
            'status' => self::string($ticket, 'status'),
            'table_id' => self::nullableString($ticket, 'table_id'),
            'table_name' => self::nullableString($ticket, 'table_name'),
            'label' => self::nullableString($ticket, 'label'),
            'staff_id' => self::string($ticket, 'staff_id'),
            'opened_at' => self::string($ticket, 'opened_at'),
            'bill_requested_at' => self::nullableString($ticket, 'bill_requested_at'),
            'lines' => self::list($ticket, 'lines'),
        ], self::list($json, 'tickets'));
    }

    /**
     * Send tickets this till opened or added to; one result per ticket.
     *
     * @param  list<array<string, mixed>>  $tickets
     * @return list<array{uuid: string|null, status: string, ticket_status: string|null, refused_lines: list<string>, reason: string|null}>
     */
    public function syncTickets(array $tickets): array
    {
        $json = $this->send(fn () => $this->authorised()->post('tickets/sync', ['tickets' => $tickets]));

        return array_map(fn (array $result): array => [
            'uuid' => self::nullableString($result, 'uuid'),
            'status' => self::string($result, 'status'),
            'ticket_status' => self::nullableString($result, 'ticket_status'),
            'refused_lines' => array_values(array_filter((array) data_get($result, 'refused_lines', []), is_string(...))),
            'reason' => self::nullableString($result, 'reason'),
        ], self::list($json, 'results'));
    }

    /**
     * Ask for an M-Pesa push to the customer's phone. The server answers at
     * once; the result comes later through payment(). Resending the same
     * UUID returns the same payment.
     */
    public function requestPayment(string $uuid, string $orderUuid, string $phone, int $amountCents): PaymentState
    {
        return self::paymentState($this->send(fn () => $this->authorised()->post('payments', [
            'uuid' => $uuid,
            'order_uuid' => $orderUuid,
            'provider' => 'mpesa_ke',
            'phone' => $phone,
            'amount_cents' => $amountCents,
        ])));
    }

    /** A scan-to-pay payment for a bill: no phone, the server makes the QR (paybill accounts only). */
    public function requestQrPayment(string $uuid, string $orderUuid, int $amountCents): PaymentState
    {
        return self::paymentState($this->send(fn () => $this->authorised()->post('payments', [
            'uuid' => $uuid,
            'order_uuid' => $orderUuid,
            'provider' => 'mpesa_ke',
            'channel' => 'qr',
            'amount_cents' => $amountCents,
        ])));
    }

    public function payment(string $uuid): PaymentState
    {
        return self::paymentState($this->send(fn () => $this->authorised()->get("payments/{$uuid}")));
    }

    private function request(string $prefix = '/api/v1'): PendingRequest
    {
        $baseUrl = config('terminal.api_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new ApiNotConfigured('VENDAPOS_API_URL is not set.');
        }

        return Http::baseUrl(rtrim($baseUrl, '/').$prefix)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('terminal.http_timeout_seconds'))
            ->connectTimeout((int) config('terminal.http_connect_timeout_seconds'));
    }

    private function authorised(string $prefix = '/api/v1'): PendingRequest
    {
        $credentials = $this->till->credentials();

        return $this->request($prefix)
            ->withToken($credentials['token'])
            ->withHeader(self::DEVICE_HEADER, $credentials['device_id']);
    }

    /**
     * @param  callable(): Response  $call
     * @return array<mixed>
     */
    private function send(callable $call): array
    {
        try {
            $response = $call();
        } catch (ConnectionException $e) {
            throw new ApiUnavailable('The server could not be reached.', previous: $e);
        }

        if ($response->successful()) {
            $json = $response->json();

            return is_array($json) ? $json : throw new ApiUnavailable('The server sent an answer that is not JSON.');
        }

        $code = is_string($response->json('code')) ? $response->json('code') : null;

        throw match (true) {
            // A suspended account refuses new M-Pesa requests; the till itself is still fine.
            $response->status() === 403 && $code === 'tenant_suspended' => new ApiRejected('This account is suspended.', 'tenant_suspended'),
            default => match ($response->status()) {
                401, 403 => new TillNotAuthorised('The server refused this till.'),
                409 => new TokenStillValid('The token has not expired yet.'),
                404 => new ApiRejected('Not found.', 'not_found'),
                422 => new ApiRejected(
                    is_string($response->json('message')) ? $response->json('message') : 'The server refused the request.',
                    is_string($response->json('reason')) ? $response->json('reason') : $code,
                ),
                default => new ApiUnavailable("The server answered {$response->status()}.", $response->status()),
            },
        };
    }

    /**
     * @param  array<mixed>  $json
     * @return array{key: string, host: string, port: int, scheme: string, channel: string, event: string}|null
     */
    private static function realtime(array $json): ?array
    {
        $realtime = data_get($json, 'realtime');

        if (! is_array($realtime)) {
            return null;
        }

        return [
            'key' => self::string($realtime, 'key'),
            'host' => self::string($realtime, 'host'),
            'port' => (int) data_get($realtime, 'port'),
            'scheme' => self::string($realtime, 'scheme'),
            'channel' => self::string($realtime, 'channel'),
            'event' => self::string($realtime, 'event'),
        ];
    }

    /** @param  array<mixed>  $json */
    private static function paymentState(array $json): PaymentState
    {
        return new PaymentState(
            uuid: self::string($json, 'uuid'),
            status: self::string($json, 'status'),
            amountCents: (int) data_get($json, 'amount_cents'),
            providerReference: self::nullableString($json, 'provider_reference'),
            failureReason: self::nullableString($json, 'failure_reason'),
            failureCode: self::nullableString($json, 'failure_code'),
            qr: is_array($json['qr'] ?? null) ? new BillQr(
                png: self::nullableString($json, 'qr.png'),
                paybill: self::nullableString($json, 'qr.paybill'),
                account: self::nullableString($json, 'qr.account'),
                amountCents: (int) data_get($json, 'amount_cents'),
            ) : null,
        );
    }

    /** @param  array<mixed>  $json */
    private static function branch(array $json): BranchInfo
    {
        return new BranchInfo(
            id: self::string($json, 'branch.id'),
            name: self::string($json, 'branch.name'),
            businessType: self::string($json, 'branch.business_type'),
            terminalMode: self::string($json, 'branch.terminal_mode'),
        );
    }

    /** @param  array<mixed>  $json */
    private static function string(array $json, string $key): string
    {
        $value = data_get($json, $key);

        return is_string($value) ? $value : throw new ApiUnavailable("The server's answer had no {$key}.");
    }

    /** @param  array<mixed>  $json */
    private static function nullableString(array $json, string $key): ?string
    {
        $value = data_get($json, $key);

        return is_string($value) ? $value : null;
    }

    /** @param  array<mixed>  $json */
    private static function date(array $json, string $key): ?CarbonImmutable
    {
        $value = data_get($json, $key);

        return is_string($value) ? CarbonImmutable::parse($value)->utc() : null;
    }

    /**
     * @param  array<mixed>  $json
     * @return list<array<mixed>>
     */
    private static function list(array $json, string $key): array
    {
        $value = data_get($json, $key);

        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }
}
