<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\TerminalMode;

it('maps restaurant to table service', function () {
    expect(BusinessType::Restaurant->terminalMode())->toBe(TerminalMode::TableService);
});

it('maps bar to tabs', function () {
    expect(BusinessType::Bar->terminalMode())->toBe(TerminalMode::Tabs);
});

it('maps salon to appointments', function () {
    expect(BusinessType::Salon->terminalMode())->toBe(TerminalMode::Appointments);
});

it('maps supermarket to multi-lane', function () {
    expect(BusinessType::Supermarket->terminalMode())->toBe(TerminalMode::MultiLane);
});

it('maps remaining types to quick sale', function (BusinessType $type) {
    expect($type->terminalMode())->toBe(TerminalMode::QuickSale);
})->with([
    BusinessType::Shop,
    BusinessType::Hardware,
    BusinessType::Pharmacy,
    BusinessType::Liquor,
    BusinessType::General,
]);

it('has 9 business types', function () {
    expect(BusinessType::cases())->toHaveCount(9);
});

it('is string-backed', function () {
    expect(BusinessType::Restaurant->value)->toBe('restaurant');
});
