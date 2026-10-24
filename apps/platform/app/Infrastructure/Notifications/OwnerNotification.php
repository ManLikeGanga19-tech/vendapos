<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A message to a tenant's owner about their account: by SMS always, and by
 * email when the owner has an address. Queued on `notifications` after the
 * change that caused it commits, written in the tenant's language
 * (Tenant::preferredLocale), and every delivery is recorded in
 * notification_deliveries.
 */
abstract class OwnerNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /** Retried while a provider is unreachable; a provider's refusal is recorded instead. */
    public int $tries = 5;

    /** @var list<int> seconds */
    public array $backoff = [60, 300, 900, 3600];

    /** Stable name for the delivery log, e.g. "tenant.suspended". */
    abstract public function key(): string;

    abstract public function toSms(Tenant $tenant): string;

    abstract public function toMail(Tenant $tenant): MailMessage;

    /** @return list<string> */
    public function via(Tenant $tenant): array
    {
        return blank($tenant->owner_email) ? [SmsChannel::class] : [SmsChannel::class, 'mail'];
    }

    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return [SmsChannel::class => 'notifications', 'mail' => 'notifications'];
    }
}
