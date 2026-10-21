<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

/** A payment UUID was reused with different details, or by another device. */
final class PaymentConflict extends RuntimeException {}
