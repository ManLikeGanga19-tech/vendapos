<?php

declare(strict_types=1);

use VendaPOS\Core\Enums\VatCategory;

it('returns 1600 basis points for standard rate', function () {
    expect(VatCategory::Standard->rate())->toBe(1600);
});

it('returns 0 basis points for zero-rated', function () {
    expect(VatCategory::ZeroRated->rate())->toBe(0);
});

it('returns 0 basis points for exempt', function () {
    expect(VatCategory::Exempt->rate())->toBe(0);
});

it('is string-backed', function () {
    expect(VatCategory::Standard->value)->toBe('standard');
});
