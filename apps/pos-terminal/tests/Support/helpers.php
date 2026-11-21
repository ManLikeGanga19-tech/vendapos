<?php

declare(strict_types=1);

use App\Api\Data\BranchInfo;
use App\Api\Data\IssuedToken;
use App\Api\Data\PairingResult;
use App\Models\Shift;
use App\Models\TillIdentity;
use App\Till\Till;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

const TEST_API_URL = 'https://api.vendapos.test';
const TEST_DEVICE_ID = '01a1644c-bbd0-72f7-a125-87653cd3a7aa';

function pairingResult(string $token = 'device-token-1', ?CarbonImmutable $expiresAt = null, string $branchId = '0199b1f2-0000-7000-8000-000000000001'): PairingResult
{
    return new PairingResult(
        deviceId: TEST_DEVICE_ID,
        deviceName: 'Till 1',
        businessName: 'Danamo Restaurant',
        branch: new BranchInfo($branchId, 'Nyali', 'restaurant', 'table_service'),
        token: new IssuedToken($token, $expiresAt ?? CarbonImmutable::now()->addHours(12)),
        tillCode: 'T1',
    );
}

function pairTill(string $token = 'device-token-1', ?CarbonImmutable $expiresAt = null): TillIdentity
{
    return app(Till::class)->pair(pairingResult($token, $expiresAt));
}

/** An open drawer shift with a KES 5,000 float, already on the server: selling needs one. */
function openTestShift(string $openedBy = '0199b1f2-0000-7000-8000-00000000b001', array $float = [1000 => 5]): Shift
{
    return Shift::query()->create([
        'id' => (string) Str::uuid7(),
        'number' => (int) Shift::query()->max('number') + 1,
        'opened_at' => now()->utc(),
        'opened_by' => $openedBy,
        'opening_counts' => $float,
        'sync_status' => Shift::SYNCED,
    ]);
}
