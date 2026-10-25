<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/** The token has not expired yet, so it is rolled over by the normal refresh instead. */
final class DeviceTokenStillValid extends RuntimeException {}
