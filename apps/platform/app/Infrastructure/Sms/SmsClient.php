<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms;

use RuntimeException;

/**
 * Sends one SMS. A refusal by the provider (bad number, no balance) comes
 * back as an unaccepted result and is not retried; a provider that cannot
 * be reached throws, so the queued notification is retried.
 */
interface SmsClient
{
    /**
     * @param  string  $to  E.164
     *
     * @throws RuntimeException
     */
    public function send(string $to, string $message): SmsResult;
}
