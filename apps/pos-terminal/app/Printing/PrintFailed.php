<?php

declare(strict_types=1);

namespace App\Printing;

use RuntimeException;

/** The printer could not be reached or refused the job; the message is safe to show staff. */
final class PrintFailed extends RuntimeException {}
