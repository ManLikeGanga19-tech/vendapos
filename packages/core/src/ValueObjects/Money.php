<?php

declare(strict_types=1);

namespace VendaPOS\Core\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable value object representing money as integer cents in KES.
 * All arithmetic uses bcmath on string-cast integers — no floats ever.
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $cents,
    ) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * From a shillings amount as typed, e.g. "1250", "1,250.5" or "1250.50".
     * Read digit by digit, never through a float; more than two decimals,
     * signs and anything else are refused rather than rounded.
     */
    public static function fromShillings(string $amount): self
    {
        $plain = str_replace(',', '', trim($amount));

        if (preg_match('/^(\d{1,13})(?:\.(\d{1,2}))?$/', $plain, $parts) !== 1) {
            throw new InvalidArgumentException("\"{$amount}\" is not an amount in shillings.");
        }

        return new self((int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0'));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self((int) bcadd((string) $this->cents, (string) $other->cents, 0));
    }

    public function subtract(self $other): self
    {
        return new self((int) bcsub((string) $this->cents, (string) $other->cents, 0));
    }

    public function multiply(int $quantity): self
    {
        return new self((int) bcmul((string) $this->cents, (string) $quantity, 0));
    }

    /**
     * Allocate this amount across N parts, distributing remainder one cent at a time.
     *
     * @param  int  $parts  Number of parts (e.g. split-bill count)
     * @return self[]
     */
    public function allocate(int $parts): array
    {
        if ($parts <= 0) {
            throw new InvalidArgumentException('Parts must be greater than zero.');
        }

        $base = (int) bcdiv((string) $this->cents, (string) $parts, 0);
        $remainder = $this->cents - ($base * $parts);

        $allocations = [];
        for ($i = 0; $i < $parts; $i++) {
            $allocations[] = new self($base + ($i < $remainder ? 1 : 0));
        }

        return $allocations;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isWholeShillings(): bool
    {
        return $this->cents % 100 === 0;
    }

    /**
     * Whole shillings for mobile-money APIs, which take no cents. Amounts with
     * cents are refused, never rounded (approved by Daniel, M1).
     */
    public function toWholeShillings(): int
    {
        if (! $this->isWholeShillings()) {
            throw new InvalidArgumentException("{$this->cents} cents is not a whole number of shillings.");
        }

        return intdiv($this->cents, 100);
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /** Shillings for a form field, the reverse of fromShillings(): "1234.56" */
    public function toShillings(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $cents = abs($this->cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Format for display: "KES 1,234.56" */
    public function format(): string
    {
        return 'KES '.number_format($this->cents / 100, 2);
    }

    public function jsonSerialize(): int
    {
        return $this->cents;
    }
}
