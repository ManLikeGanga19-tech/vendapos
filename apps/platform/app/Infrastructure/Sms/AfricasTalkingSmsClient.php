<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Africa's Talking bulk SMS (POST /version1/messaging, form-encoded, apikey
 * header), as used by their official SDK. The username "sandbox" sends to
 * the sandbox, which only reaches the simulator.
 */
final class AfricasTalkingSmsClient implements SmsClient
{
    /** Per-recipient codes that mean the message was taken: Processed, Sent, Queued. */
    private const array ACCEPTED_CODES = [100, 101, 102];

    public function send(string $to, string $message): SmsResult
    {
        /** @var array{username: string|null, api_key: string|null, sender_id: string|null} $config */
        $config = config('vendapos.sms.africastalking');
        $username = (string) $config['username'];

        if ($username === '' || (string) $config['api_key'] === '') {
            throw new RuntimeException('Africa’s Talking username or API key is not configured.');
        }

        $form = ['username' => $username, 'to' => $to, 'message' => $message];

        // Until a sender ID is registered, AT sends from its shared one.
        if ((string) $config['sender_id'] !== '') {
            $form['from'] = (string) $config['sender_id'];
        }

        $domain = $username === 'sandbox' ? 'api.sandbox.africastalking.com' : 'api.africastalking.com';

        try {
            $response = Http::asForm()->acceptJson()->timeout(15)
                ->withHeaders(['apikey' => (string) $config['api_key']])
                ->post("https://{$domain}/version1/messaging", $form);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Africa’s Talking is unreachable: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Africa’s Talking answered HTTP {$response->status()}.");
        }

        $recipient = $response->json('SMSMessageData.Recipients.0');

        // No recipient: the whole request was refused (e.g. an unknown sender ID); AT says why in Message.
        if (! is_array($recipient)) {
            return SmsResult::refused((string) ($response->json('SMSMessageData.Message') ?? 'No recipient in the response'));
        }

        if (in_array((int) ($recipient['statusCode'] ?? 0), self::ACCEPTED_CODES, true)) {
            return SmsResult::accepted(isset($recipient['messageId']) ? (string) $recipient['messageId'] : null);
        }

        return SmsResult::refused(sprintf('%s (%s)', (string) ($recipient['status'] ?? 'Refused'), (string) ($recipient['statusCode'] ?? '?')));
    }
}
