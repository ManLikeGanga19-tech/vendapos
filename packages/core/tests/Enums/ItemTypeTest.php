<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\ItemType;

it('tracks stock only for products and bundles', function (ItemType $type, bool $tracks) {
    expect($type->tracksStock())->toBe($tracks);
})->with([
    [ItemType::Product, true],
    [ItemType::Bundle, true],
    [ItemType::Service, false],
    [ItemType::Modifier, false],
]);
