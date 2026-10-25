<?php

declare(strict_types=1);

namespace VendaPOS\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number that can be normalised to E.164 (e.g. +254712345678).
 *
 * Kenyan local formats (0712 345 678, 0110 123 456) and 254… without the
 * plus are accepted and assumed to be Kenyan; anything else must already
 * carry an international dialling code.
 */
final readonly class PhoneNumber implements ValidationRule
{
    public function __construct(
        private string $message = 'The :attribute must be a valid phone number.',
    ) {}

    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/[\s().-]/', '', $value) ?? '';

        $e164 = match (true) {
            (bool) preg_match('/^0[17]\d{8}$/', $digits) => '+254'.substr($digits, 1),
            (bool) preg_match('/^254[17]\d{8}$/', $digits) => '+'.$digits,
            str_starts_with($digits, '00') => '+'.substr($digits, 2),
            default => $digits,
        };

        return preg_match('/^\+[1-9]\d{7,14}$/', $e164) === 1 ? $e164 : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail($this->message);
        }
    }
}
