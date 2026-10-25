<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Notifications\OwnerNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells the owner their account is suspended, what still works, and the date their data will be deleted. */
final class TenantSuspended extends OwnerNotification
{
    public function __construct(public readonly CarbonImmutable $purgeAfter) {}

    public function key(): string
    {
        return 'tenant.suspended';
    }

    public function toSms(Tenant $tenant): string
    {
        return __('notifications.tenant_suspended.sms', [
            'business' => $tenant->name,
            'date' => OwnerNoticeText::date($this->purgeAfter),
        ]).OwnerNoticeText::contactSentence();
    }

    public function toMail(Tenant $tenant): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('notifications.tenant_suspended.subject', ['business' => $tenant->name]))
            ->greeting(__('notifications.greeting', ['name' => $tenant->owner_name]))
            ->line(__('notifications.tenant_suspended.intro', ['business' => $tenant->name]));

        if (filled($tenant->suspension_reason)) {
            $mail->line(__('notifications.tenant_suspended.reason', ['reason' => $tenant->suspension_reason]));
        }

        return $mail
            ->line(__('notifications.tenant_suspended.still_works'))
            ->line(__('notifications.tenant_suspended.blocked'))
            ->line(__('notifications.tenant_suspended.purge', ['date' => OwnerNoticeText::date($this->purgeAfter)]))
            ->line(OwnerNoticeText::contactSentence());
    }
}
