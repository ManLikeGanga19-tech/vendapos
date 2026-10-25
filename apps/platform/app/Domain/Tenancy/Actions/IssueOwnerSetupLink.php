<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffSetupLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gives the tenant's owner a one-time link to choose a username and password,
 * creating the owner's staff record the first time. Any earlier open link
 * stops working. Run inside the tenant's context.
 *
 * Returns the token; only its hash is stored, so it cannot be shown again.
 */
final class IssueOwnerSetupLink
{
    public const VALID_HOURS = 48;

    public function handle(string $ownerName, string $issuedBy): string
    {
        $token = Str::random(48);

        DB::transaction(function () use ($ownerName, $issuedBy, $token) {
            // The owner role row doubles as a lock so two admins cannot both
            // create a first owner at the same moment.
            DB::table('roles')->where(['name' => 'owner', 'guard_name' => 'tenant'])->lockForUpdate()->first();

            $owner = Staff::role('owner')->oldest()->first();

            if ($owner === null) {
                $owner = Staff::create(['name' => $ownerName]);
                $owner->assignRole('owner');
            }

            $owner->setupLinks()->open()->update(['expires_at' => now()]);

            StaffSetupLink::create([
                'staff_id' => $owner->id,
                'token_hash' => hash('sha256', $token),
                'issued_by' => $issuedBy,
                'expires_at' => now()->addHours(self::VALID_HOURS),
            ]);
        });

        return $token;
    }
}
