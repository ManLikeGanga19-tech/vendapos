<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Data\BranchInfo;
use App\Api\Data\CatalogueSnapshot;
use App\Api\Data\DeviceStatus;
use App\Api\Data\IssuedToken;
use App\Api\Data\PairingResult;
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
        );
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

    private function request(): PendingRequest
    {
        $baseUrl = config('terminal.api_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new ApiNotConfigured('VENDAPOS_API_URL is not set.');
        }

        return Http::baseUrl(rtrim($baseUrl, '/').'/api/v1')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('terminal.http_timeout_seconds'))
            ->connectTimeout((int) config('terminal.http_connect_timeout_seconds'));
    }

    private function authorised(): PendingRequest
    {
        $credentials = $this->till->credentials();

        return $this->request()
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

        throw match ($response->status()) {
            401, 403 => new TillNotAuthorised('The server refused this till.'),
            409 => new TokenStillValid('The token has not expired yet.'),
            404 => new ApiRejected('Not found.', 'not_found'),
            422 => new ApiRejected(
                is_string($response->json('message')) ? $response->json('message') : 'The server refused the request.',
                is_string($response->json('reason')) ? $response->json('reason') : null,
            ),
            default => new ApiUnavailable("The server answered {$response->status()}.", $response->status()),
        };
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
