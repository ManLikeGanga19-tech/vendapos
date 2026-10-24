<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Notifications\OwnerNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells the owner their data is deleted and until when the encrypted copy is kept. */
final class TenantPurged extends OwnerNotification
{
    public function __construct(public readonly CarbonImmutable $exportKeptUntil) {}

    public function key(): string
    {
        return 'tenant.purged';
    }

    public function toSms(Tenant $tenant): string
    {
        return __('notifications.tenant_purged.sms', [
            'business' => $tenant->name,
            'date' => OwnerNoticeText::date($this->exportKeptUntil),
        ]).OwnerNoticeText::contactSentence();
    }

    public function toMail(Tenant $tenant): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.tenant_purged.subject', ['business' => $tenant->name]))
            ->greeting(__('notifications.greeting', ['name' => $tenant->owner_name]))
            ->line(__('notifications.tenant_purged.body', [
                'business' => $tenant->name,
                'date' => OwnerNoticeText::date($this->exportKeptUntil),
            ]))
            ->line(OwnerNoticeText::contactSentence());
    }
}
