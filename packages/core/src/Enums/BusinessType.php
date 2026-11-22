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

    /**
     * Whether this business type's module is built, so it can be given to a
     * tenant and picked for a branch. The rest show as "coming soon" and
     * switch on as each module is built (Daniel, T7).
     */
    public function isAvailable(): bool
    {
        return in_array($this, [self::Restaurant, self::Shop, self::General], true);
    }

    /** @return list<self> */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->isAvailable()));
    }

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
