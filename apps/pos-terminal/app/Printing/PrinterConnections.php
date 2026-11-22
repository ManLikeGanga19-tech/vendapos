<?php

declare(strict_types=1);

namespace App\Printing;

use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

/** Opens the connection the settings describe. Tests bind a fake that records the bytes. */
class PrinterConnections
{
    /** @throws PrintFailed */
    public function open(PrinterSettings $settings): PrintConnector
    {
        try {
            return match ($settings->connection) {
                PrinterSettings::WINDOWS => new WindowsPrintConnector($settings->target),
                PrinterSettings::DEVICE => new FilePrintConnector($settings->target),
                PrinterSettings::NETWORK => new NetworkPrintConnector(...self::hostAndPort($settings->target)),
                default => throw new PrintFailed('No printer is set up on this till.'),
            };
        } catch (PrintFailed $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PrintFailed($e->getMessage(), previous: $e);
        }
    }

    /** @return array{0: string, 1: int} */
    private static function hostAndPort(string $target): array
    {
        [$host, $port] = array_pad(explode(':', $target, 2), 2, '9100');

        return [$host, (int) $port ?: 9100];
    }
}
