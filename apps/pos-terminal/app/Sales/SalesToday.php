<?php

declare(strict_types=1);

namespace App\Sales;

use App\Api\Exceptions\ApiNotConfigured;
use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\TillNotAuthorised;
use App\Api\VendaApi;
use App\Livewire\StatusBar;
use App\Models\Order;
use App\Security\KeyringUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use VendaPOS\Core\Enums\OrderStatus;

/**
 * The sales of the business day (Africa/Nairobi) a cashier can look through
 * to find one to void: online, every till of the branch (from the server)
 * plus this till's sales not synced yet; offline, this till's own only.
 */
final class SalesToday
{
    private const string CACHE_KEY = 'till.sales_today';

    /** Long enough that tapping through the screen doesn't ask the server each time. */
    private const int CACHE_SECONDS = 15;

    public function __construct(private readonly VendaApi $api) {}

    public static function startOfDay(): CarbonImmutable
    {
        return CarbonImmutable::now(StatusBar::TIMEZONE)->startOfDay()->utc();
    }

    /** @return array{online: bool, sales: list<SaleSummary>} */
    public function list(): array
    {
        $local = Order::query()
            ->with(['lines', 'tenders'])
            ->where('placed_at', '>=', self::startOfDay())
            ->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Voided->value])
            ->get()
            ->keyBy('id');

        try {
            /** @var list<array<string, mixed>> $server */
            $server = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->api->salesToday());
            $online = true;
        } catch (ApiUnavailable|TillNotAuthorised|ApiNotConfigured|KeyringUnavailable) {
            $server = [];
            $online = false;
        }

        $sales = [];

        foreach ($server as $sale) {
            $order = $local->pull((string) ($sale['id'] ?? ''));

            // This till's own sale: its own copy, unless the server knows it was voided since (by another till).
            $sales[] = $order instanceof Order && ! ($order->status === OrderStatus::Completed->value && $sale['status'] === OrderStatus::Voided->value)
                ? SaleSummary::fromOrder($order)
                : SaleSummary::fromServer($sale, mine: $order instanceof Order);
        }

        // Not on the server yet (offline, or not synced so far): this till's copy.
        foreach ($local as $order) {
            $sales[] = SaleSummary::fromOrder($order);
        }

        usort($sales, fn (SaleSummary $a, SaleSummary $b) => $b->placedAt <=> $a->placedAt);

        return ['online' => $online, 'sales' => $sales];
    }

    public function find(string $saleId): ?SaleSummary
    {
        foreach ($this->list()['sales'] as $sale) {
            if ($sale->id === $saleId) {
                return $sale;
            }
        }

        return null;
    }

    /** After a void, so the list shows it at once. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
