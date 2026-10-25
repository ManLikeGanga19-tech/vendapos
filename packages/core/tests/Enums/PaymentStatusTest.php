<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\PaymentStatus;

it('treats every status but pending as final', function (PaymentStatus $status, bool $final) {
    expect($status->isFinal())->toBe($final);
})->with([
    [PaymentStatus::Pending, false],
    [PaymentStatus::Succeeded, true],
    [PaymentStatus::Failed, true],
    [PaymentStatus::Cancelled, true],
    [PaymentStatus::TimedOut, true],
]);
