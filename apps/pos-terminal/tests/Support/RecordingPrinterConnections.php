<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Printing\PrinterConnections;
use App\Printing\PrinterSettings;
use App\Printing\PrintFailed;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;

/** Stands in for the USB printer: keeps every printed job's bytes, or fails like an unplugged one. */
final class RecordingPrinterConnections extends PrinterConnections
{
    /** @var list<string> */
    public static array $jobs = [];

    public static bool $unplugged = false;

    public function open(PrinterSettings $settings): PrintConnector
    {
        if (self::$unplugged) {
            throw new PrintFailed('Printer not found');
        }

        return new class extends MemoryPrintConnector
        {
            public function finalize(): void
            {
                RecordingPrinterConnections::$jobs[] = $this->getData();
                parent::finalize();
            }
        };
    }
}
