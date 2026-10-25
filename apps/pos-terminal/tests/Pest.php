<?php

declare(strict_types=1);

use App\Security\SecretBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSecretBox;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Arch');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        config(['terminal.api_url' => TEST_API_URL]);
        FakeSecretBox::$available = true;
        $this->app->bind(SecretBox::class, FakeSecretBox::class);
    })
    ->in('Feature');

require_once __DIR__.'/Support/helpers.php';
