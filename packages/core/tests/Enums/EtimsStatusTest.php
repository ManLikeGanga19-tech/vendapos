<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\EtimsStatus;

it('treats every status but pending as final', function (EtimsStatus $status, bool $final) {
    expect($status->isFinal())->toBe($final);
})->with([
    [EtimsStatus::Pending, false],
    [EtimsStatus::NotRequired, true],
    [EtimsStatus::Submitted, true],
    [EtimsStatus::Failed, true],
]);

it('stores stable string values', function () {
    expect(array_map(fn (EtimsStatus $s) => $s->value, EtimsStatus::cases()))
        ->toBe(['pending', 'not_required', 'submitted', 'failed']);
});
