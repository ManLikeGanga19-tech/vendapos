<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

use RuntimeException;

/** No answer we can use right now (offline, timeout, server error, rate limit): try again later. */
final class ApiUnavailable extends RuntimeException {}
