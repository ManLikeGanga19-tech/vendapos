<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Domain\Tenancy\Data\DeviceEnrolment;
use App\Domain\Tenancy\Exceptions\PairingCodeRejected;
use App\Domain\Tenancy\Models\DevicePairingCode;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Pairs a till with the branch its code was issued for: enrols the device
 * and spends the code, together, so a code pairs exactly one till even when
 * two tills race with it.
 */
final class RedeemPairingCode
{
    public function __construct(private readonly EnrolDevice $enrolDevice) {}

    public function handle(string $typedCode): DeviceEnrolment
    {
        $hash = IssuePairingCode::hash($typedCode);

        // Central: the code, the device and its token all live there.
        return DB::connection((new DevicePairingCode)->getConnectionName())->transaction(function () use ($hash) {
            $code = DevicePairingCode::query()->where('code_hash', $hash)->lockForUpdate()->first();

            if ($code === null) {
                throw new PairingCodeRejected('unknown');
            }

            if ($code->used_at !== null) {
                throw new PairingCodeRejected('used');
            }

            if ($code->expires_at->isPast()) {
                throw new PairingCodeRejected('expired');
            }

            $tenant = Tenant::query()->findOrFail($code->tenant_id);

            if (! $tenant->isActive()) {
                throw new PairingCodeRejected('tenant_inactive');
            }

            $enrolment = $this->enrolDevice->handle($tenant, $code->branch_id, $code->device_name);
            $code->forceFill(['used_at' => now(), 'device_id' => $enrolment->device->id])->save();

            return $enrolment;
        });
    }
}
