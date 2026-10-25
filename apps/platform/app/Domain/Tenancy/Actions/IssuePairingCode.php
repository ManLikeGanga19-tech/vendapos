<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Exceptions\StaffChangeRefused;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\DevicePairingCode;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use InvalidArgumentException;

/**
 * Makes a one-time code that pairs a new till with one of the branches the
 * person asking looks after, returned once for them to type on the till.
 *
 * Eight characters from an alphabet without look-alikes (no 0/O, 1/I/L, U):
 * 30^8 ≈ 6.6 × 10^11 codes, against a strict limit on guesses and a short
 * life (vendapos.pairing_code_minutes). Run inside the tenant's context.
 */
final class IssuePairingCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const LENGTH = 8;

    public function handle(Tenant $tenant, Staff $actor, string $branchId, string $deviceName): string
    {
        if (! $tenant->isActive()) {
            throw new InvalidArgumentException("Tenant [{$tenant->getTenantKey()}] is {$tenant->status}; no new devices can be paired.");
        }

        if (! $actor->is_active || ! in_array($branchId, $actor->visibleBranchIds(), true) || ! Branch::query()->whereKey($branchId)->exists()) {
            throw new StaffChangeRefused(StaffChangeRefused::BRANCH_NOT_ALLOWED);
        }

        $code = self::generate();

        DevicePairingCode::create([
            'tenant_id' => $tenant->getTenantKey(),
            'branch_id' => $branchId,
            'device_name' => trim($deviceName),
            'code_hash' => self::hash($code),
            'issued_by' => $actor->id,
            'expires_at' => now()->addMinutes((int) config('vendapos.pairing_code_minutes')),
        ]);

        return $code;
    }

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /** As people type it: any case, with spaces or a dash between the halves. */
    public static function normalise(string $typed): string
    {
        return strtoupper((string) preg_replace('/[\s-]/', '', $typed));
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalise($code));
    }

    /** "ABCD-EFGH", easier to read out and type. */
    public static function format(string $code): string
    {
        return substr($code, 0, 4).'-'.substr($code, 4);
    }
}
