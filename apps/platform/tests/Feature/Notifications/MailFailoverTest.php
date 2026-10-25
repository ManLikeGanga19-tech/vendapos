<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\FailoverTransport;

it('sends through Amazon SES and fails over to Resend', function () {
    config([
        'services.ses' => ['key' => 'test-key', 'secret' => 'test-secret', 'region' => 'eu-west-1'],
        'services.resend.key' => 're_test_key',
    ]);

    $transport = Mail::mailer('failover')->getSymfonyTransport();

    expect(config('mail.mailers.failover.mailers'))->toBe(['ses', 'resend'])
        ->and($transport)->toBeInstanceOf(FailoverTransport::class)
        ->and((string) $transport)->toStartWith('failover(ses')
        ->and((string) $transport)->toContain('resend');
});

it('sends as VendaPOS from the transactional mail domain by default', function () {
    expect(config('mail.from.address'))->toEndWith('@mail.vendapos.com');
})->skip(fn () => env('MAIL_FROM_ADDRESS') !== null, 'MAIL_FROM_ADDRESS is set in this environment');
