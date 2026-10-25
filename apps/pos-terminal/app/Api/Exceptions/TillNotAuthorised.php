<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

use RuntimeException;

/** The server refused the till's token (401/403). */
final class TillNotAuthorised extends RuntimeException {}
