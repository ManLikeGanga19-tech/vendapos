<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Data;

use App\Domain\Tenancy\Models\Device;

/** A newly enrolled device and its token, which is shown exactly once. */
final readonly class DeviceEnrolment
{
    public function __construct(
        public Device $device,
        public string $plainTextToken,
    ) {}
}
