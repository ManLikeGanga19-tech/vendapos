<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\VendaApi;
use App\Models\StaffMember;
use App\Models\SyncState;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the local staff list with the server's. The list is small and
 * comes in full, so someone deactivated on the server disappears here too.
 */
final class SyncStaff
{
    public const string SYNCED_AT = 'staff.synced_at';

    public function __construct(private readonly VendaApi $api) {}

    public function handle(): void
    {
        $staff = $this->api->staff();

        DB::transaction(function () use ($staff): void {
            StaffMember::query()->delete();

            foreach ($staff as $member) {
                StaffMember::query()->create($member);
            }

            SyncState::write(self::SYNCED_AT, now()->toIso8601String());
        });
    }
}
