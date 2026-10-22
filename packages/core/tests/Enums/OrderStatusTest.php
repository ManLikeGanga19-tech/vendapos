<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\OrderStatus;

it('only lets a completed order become voided', function (OrderStatus $from, OrderStatus $to, bool $allowed) {
    expect($from->canBecome($to))->toBe($allowed);
})->with([
    [OrderStatus::Completed, OrderStatus::Voided, true],
    [OrderStatus::Completed, OrderStatus::Cancelled, false],
    [OrderStatus::Voided, OrderStatus::Completed, false],
    [OrderStatus::Voided, OrderStatus::Cancelled, false],
    [OrderStatus::Cancelled, OrderStatus::Completed, false],
    [OrderStatus::Cancelled, OrderStatus::Voided, false],
]);

it('treats a resent "completed" after a void as a stale retry', function () {
    expect(OrderStatus::Voided->hasMovedPast(OrderStatus::Completed))->toBeTrue()
        ->and(OrderStatus::Cancelled->hasMovedPast(OrderStatus::Completed))->toBeFalse()
        ->and(OrderStatus::Completed->hasMovedPast(OrderStatus::Cancelled))->toBeFalse();
});

it('stores stable string values', function () {
    expect(array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()))->toBe(['completed', 'voided', 'cancelled']);
});
