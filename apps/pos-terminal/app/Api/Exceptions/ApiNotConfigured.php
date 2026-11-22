<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

use RuntimeException;

/** VENDAPOS_API_URL was not set when this till was built. */
final class ApiNotConfigured extends RuntimeException {}
