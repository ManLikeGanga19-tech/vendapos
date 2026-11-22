<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

/** The provider could not be reached or failed on its side; worth trying again later. */
final class ProviderUnreachable extends RuntimeException {}
