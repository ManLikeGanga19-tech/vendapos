<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** For dev and tests: accepts every message, writes it to the log and keeps it for assertions. */
final class FakeSmsClient implements SmsClient
{
    /** @var list<array{to: string, message: string}> */
    private array $sent = [];

    public function send(string $to, string $message): SmsResult
    {
        $this->sent[] = ['to' => $to, 'message' => $message];
        Log::info('sms.fake_sent', ['to' => $to, 'message' => $message]);

        return SmsResult::accepted('fake-'.Str::uuid());
    }

    /** @return list<array{to: string, message: string}> */
    public function sent(): array
    {
        return $this->sent;
    }
}
