<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use RuntimeException;

/** The tenant is not in the state this change starts from (e.g. suspending one that is already suspended). */
final class TenantStatusConflict extends RuntimeException {}
