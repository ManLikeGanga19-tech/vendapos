<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\StaffSetupLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Uses a one-time set-up link: the staff member's chosen username and
 * password are saved and every open link of theirs is closed. Returns null
 * when the link is unknown, used or expired. Run inside the tenant's context.
 */
final class CompleteStaffSetup
{
    /** Lower-case letters, digits, dots and underscores; 3 to 30 characters. */
    public const USERNAME_PATTERN = '/^[a-z0-9._]{3,30}$/';

    public function findOpenLink(#[SensitiveParameter] string $token): ?StaffSetupLink
    {
        return StaffSetupLink::open()->where('token_hash', hash('sha256', $token))->first();
    }

    public function handle(#[SensitiveParameter] string $token, string $username, #[SensitiveParameter] string $password): ?Staff
    {
        return DB::transaction(function () use ($token, $username, $password): ?Staff {
            $link = StaffSetupLink::open()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($link === null) {
                return null;
            }

            $staff = $link->staff;
            $staff->forceFill([
                'username' => Str::lower(trim($username)),
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $staff->setupLinks()->open()->update(['used_at' => now()]);

            return $staff;
        });
    }
}
