<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

use RuntimeException;

/** The server understood the request and said no (422). The message is safe to show. */
final class ApiRejected extends RuntimeException {}
