<?php

declare(strict_types=1);

namespace App\Printing;

use App\Models\Preference;

/**
 * How this till reaches its receipt printer. Generic USB thermal printers
 * speak ESC/POS: on Windows through the printer's share name, on the Linux
 * terminal through its device file, or over the network on port 9100.
 */
final readonly class PrinterSettings
{
    public const string NONE = 'none';

    /** A printer installed and shared in Windows; target is its share name. */
    public const string WINDOWS = 'windows';

    /** A USB printer's device file on Linux, e.g. /dev/usb/lp0. */
    public const string DEVICE = 'device';

    /** A network printer; target is host or host:port (default 9100). */
    public const string NETWORK = 'network';

    public const array CONNECTIONS = [self::NONE, self::WINDOWS, self::DEVICE, self::NETWORK];

    /** Characters per line in the printer's normal font: 48 on 80 mm paper, 32 on 58 mm. */
    public const array COLUMNS = [80 => 48, 58 => 32];

    public function __construct(
        public string $connection,
        public string $target,
        public int $paperMm,
    ) {}

    public static function current(): self
    {
        $paper = (int) (Preference::read('printer.paper_mm') ?? 80);

        return new self(
            connection: Preference::read('printer.connection') ?? self::NONE,
            target: Preference::read('printer.target') ?? '',
            paperMm: isset(self::COLUMNS[$paper]) ? $paper : 80,
        );
    }

    public function save(): void
    {
        Preference::write('printer.connection', $this->connection);
        Preference::write('printer.target', $this->target);
        Preference::write('printer.paper_mm', (string) $this->paperMm);
    }

    public function isSet(): bool
    {
        return $this->connection !== self::NONE && $this->target !== '';
    }

    public function columns(): int
    {
        return self::COLUMNS[$this->paperMm];
    }
}
