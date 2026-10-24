<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Sms\SmsResult;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Events\NotificationSent;

/** Writes one notification_deliveries row for each owner message a provider took or refused. */
final class RecordNotificationDelivery
{
    public function handle(NotificationSent $event): void
    {
        if (! $event->notification instanceof OwnerNotification || ! $event->notifiable instanceof Tenant) {
            return;
        }

        $row = match (true) {
            $event->response instanceof SmsResult => [
                'channel' => 'sms',
                'status' => $event->response->accepted ? NotificationDelivery::SENT : NotificationDelivery::REFUSED,
                'provider_message_id' => $event->response->messageId,
                'error' => $event->response->error,
            ],
            $event->response instanceof SentMessage => [
                'channel' => 'mail',
                'status' => NotificationDelivery::SENT,
                'provider_message_id' => $event->response->getMessageId(),
                'error' => null,
            ],
            default => null, // nothing was handed to a provider (no address)
        };

        if ($row === null) {
            return;
        }

        NotificationDelivery::query()->create([
            ...$row,
            'tenant_id' => $event->notifiable->getTenantKey(),
            'notification' => $event->notification->key(),
            'recipient' => (string) $event->notifiable->routeNotificationFor($row['channel'] === 'sms' ? 'sms' : 'mail', $event->notification),
        ]);
    }
}
