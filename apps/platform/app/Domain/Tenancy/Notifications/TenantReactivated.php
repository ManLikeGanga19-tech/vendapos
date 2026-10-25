<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Notifications\OwnerNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells the owner the suspension is lifted and nothing will be deleted. */
final class TenantReactivated extends OwnerNotification
{
    public function key(): string
    {
        return 'tenant.reactivated';
    }

    public function toSms(Tenant $tenant): string
    {
        return __('notifications.tenant_reactivated.sms', ['business' => $tenant->name]);
    }

    public function toMail(Tenant $tenant): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.tenant_reactivated.subject', ['business' => $tenant->name]))
            ->greeting(__('notifications.greeting', ['name' => $tenant->owner_name]))
            ->line(__('notifications.tenant_reactivated.body', ['business' => $tenant->name]));
    }
}
