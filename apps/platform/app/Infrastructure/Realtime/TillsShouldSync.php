<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * "Something you hold changed: sync now", sent to tills over Reverb. It
 * carries no data, only what kind of thing changed; the till fetches the
 * change through the normal API. Broadcast from BroadcastTillSync, which is
 * already queued, hence ShouldBroadcastNow.
 */
final class TillsShouldSync implements ShouldBroadcastNow
{
    public const string MENU = 'menu';

    public const string STAFF = 'staff';

    /** The till itself: revoked, renamed or moved. */
    public const string DEVICE = 'device';

    /** The business: suspended or reactivated. */
    public const string ACCOUNT = 'account';

    /** A branch's floor plan: areas and tables (table service). */
    public const string TABLES = 'tables';

    public const array WHAT = [self::MENU, self::STAFF, self::DEVICE, self::ACCOUNT, self::TABLES];

    /** @param  list<string>  $deviceIds */
    public function __construct(public readonly array $deviceIds, public readonly string $what) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(fn (string $id) => new PrivateChannel("till.{$id}"), $this->deviceIds);
    }

    public function broadcastAs(): string
    {
        return 'till.sync';
    }

    /** @return array{what: string} */
    public function broadcastWith(): array
    {
        return ['what' => $this->what];
    }
}
