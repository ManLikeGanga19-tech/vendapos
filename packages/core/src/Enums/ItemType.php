<?php

declare(strict_types=1);

namespace VendaPOS\Core\Enums;

/** What a catalogue item is (architecture §7.5). */
enum ItemType: string
{
    case Product = 'product';
    case Service = 'service';
    case Bundle = 'bundle';
    case Modifier = 'modifier';

    /** Products and bundles hold stock; services and modifiers don't by themselves. */
    public function tracksStock(): bool
    {
        return match ($this) {
            self::Product, self::Bundle => true,
            self::Service, self::Modifier => false,
        };
    }
}
