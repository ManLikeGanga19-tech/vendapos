<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Notifications\OwnerNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the owner a payment account's keys, default or branches changed, so a
 * change they did not make (money redirected to another paybill) is caught.
 */
final class PaymentAccountChangedNotice extends OwnerNotification
{
    /** @param string $action PaymentAccountChange::ACTION_* */
    public function __construct(
        public readonly string $accountName,
        public readonly string $action,
        public readonly string $changedBy,
        public readonly CarbonImmutable $changedAt,
    ) {}

    public function key(): string
    {
        return 'payment_account.changed';
    }

    public function toSms(Tenant $tenant): string
    {
        return __('notifications.payment_account_changed.sms', $this->replacements($tenant)).OwnerNoticeText::contactSentence();
    }

    public function toMail(Tenant $tenant): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.payment_account_changed.subject', $this->replacements($tenant)))
            ->greeting(__('notifications.greeting', ['name' => $tenant->owner_name]))
            ->line(__('notifications.payment_account_changed.body', $this->replacements($tenant)))
            ->line(__('notifications.payment_account_changed.not_you'))
            ->line(OwnerNoticeText::contactSentence());
    }

    /** @return array<string, string> */
    private function replacements(Tenant $tenant): array
    {
        return [
            'business' => (string) $tenant->name,
            'account' => $this->accountName,
            'change' => __('notifications.payment_account_changed.actions.'.$this->action),
            'name' => $this->changedBy,
            'time' => $this->changedAt->setTimezone((string) config('vendapos.business_timezone'))->format('H:i'),
            'date' => OwnerNoticeText::date($this->changedAt),
        ];
    }
}
