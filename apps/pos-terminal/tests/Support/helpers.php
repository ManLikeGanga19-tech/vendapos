<?php

declare(strict_types=1);

use App\Api\Data\BranchInfo;
use App\Api\Data\IssuedToken;
use App\Api\Data\PairingResult;
use App\Models\TillIdentity;
use App\Till\Till;
use Carbon\CarbonImmutable;

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
    );
}

function pairTill(string $token = 'device-token-1', ?CarbonImmutable $expiresAt = null): TillIdentity
{
    return app(Till::class)->pair(pairingResult($token, $expiresAt));
}
