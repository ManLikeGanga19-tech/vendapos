<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Notifications\OwnerNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/** Reminds the owner of a suspended account how many days are left before its data is deleted. */
final class TenantPurgeReminder extends OwnerNotification
{
    public function __construct(public readonly int $daysLeft, public readonly CarbonImmutable $purgeAfter) {}

    public function key(): string
    {
        return "tenant.purge_reminder.{$this->daysLeft}";
    }

    public function toSms(Tenant $tenant): string
    {
        return trans_choice('notifications.tenant_purge_reminder.sms', $this->daysLeft, [
            'business' => $tenant->name,
            'days' => $this->daysLeft,
            'date' => OwnerNoticeText::date($this->purgeAfter),
        ]).OwnerNoticeText::contactSentence();
    }

    public function toMail(Tenant $tenant): MailMessage
    {
        return (new MailMessage)
            ->subject(trans_choice('notifications.tenant_purge_reminder.subject', $this->daysLeft, ['business' => $tenant->name, 'days' => $this->daysLeft]))
            ->greeting(__('notifications.greeting', ['name' => $tenant->owner_name]))
            ->line(trans_choice('notifications.tenant_purge_reminder.body', $this->daysLeft, [
                'business' => $tenant->name,
                'days' => $this->daysLeft,
                'date' => OwnerNoticeText::date($this->purgeAfter),
            ]))
            ->line(OwnerNoticeText::contactSentence());
    }
}
