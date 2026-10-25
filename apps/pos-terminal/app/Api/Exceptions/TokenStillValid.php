<?php

declare(strict_types=1);

namespace App\Api\Exceptions;

use RuntimeException;

/** Renewal was asked for a token that has not expired yet (409): refresh it instead. */
final class TokenStillValid extends RuntimeException {}
