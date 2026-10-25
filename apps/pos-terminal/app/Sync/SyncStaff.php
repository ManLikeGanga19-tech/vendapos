<?php

declare(strict_types=1);

namespace App\Sync;

use App\Api\VendaApi;
use App\Models\PinAttempt;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Security\SecretBox;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the local staff list with the server's. The list is small and
 * comes in full, so someone deactivated on the server disappears here too.
 * PIN hashes are sealed by the keyring before they are stored.
 */
final class SyncStaff
{
    public const string SYNCED_AT = 'staff.synced_at';

    public function __construct(private readonly VendaApi $api, private readonly SecretBox $secrets) {}

    public function handle(): void
    {
        // Seal first: the keyring calls must not run inside the transaction.
        $staff = array_map(fn (array $member): array => self::row($member, $this->secrets), $this->api->staff());

        DB::transaction(function () use ($staff): void {
            StaffMember::query()->delete();

            foreach ($staff as $member) {
                StaffMember::query()->create($member);
            }

            // Lockouts of people no longer on the list are dropped with them.
            PinAttempt::query()->whereNotIn('staff_id', array_column($staff, 'id'))->delete();

            SyncState::write(self::SYNCED_AT, now()->toIso8601String());
        });
    }

    /**
     * @param  array{id: string, name: string, role: string, permissions: list<string>, pin_hash: string, pin_length: int}  $member
     * @return array{id: string, name: string, role: string, permissions: list<string>, pin_hash_sealed: string, pin_length: int}
     */
    public static function row(array $member, SecretBox $secrets): array
    {
        return [
            'id' => $member['id'],
            'name' => $member['name'],
            'role' => $member['role'],
            'permissions' => $member['permissions'],
            'pin_hash_sealed' => $secrets->encrypt($member['pin_hash']),
            'pin_length' => $member['pin_length'],
        ];
    }
}
