<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

enum BusinessType: string
{
    case Restaurant = 'restaurant';
    case Bar = 'bar';
    case Salon = 'salon';
    case Shop = 'shop';
    case Supermarket = 'supermarket';
    case Hardware = 'hardware';
    case Pharmacy = 'pharmacy';
    case Liquor = 'liquor';
    case General = 'general';

    public function terminalMode(): TerminalMode
    {
        return match ($this) {
            self::Restaurant => TerminalMode::TableService,
            self::Bar => TerminalMode::Tabs,
            self::Salon => TerminalMode::Appointments,
            self::Supermarket => TerminalMode::MultiLane,
            default => TerminalMode::QuickSale,
        };
    }
}
