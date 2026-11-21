<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Notifications\OwnerNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * One digest of new voids to check (approved by someone who could not
 * approve, voided on a later day, a staff member voiding too much), at most
 * every half hour.
 */
final class VoidChecksNotice extends OwnerNotification
{
    /** @param array<string, int> $counts kind (VoidCheck::KINDS) => how many are new */
    public function __construct(public readonly array $counts) {}

    public function key(): string
    {
        return 'void.checks';
    }

    public function toSms(Tenant $tenant): string
    {
        return trans_choice('notifications.void_checks.sms', $this->total(), ['business' => $tenant->name, 'count' => $this->total()]);
    }

    public function toMail(Tenant $tenant): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(trans_choice('notifications.void_checks.subject', $this->total(), ['business' => $tenant->name, 'count' => $this->total()]))
            ->greeting(__('notifications.greeting', ['name' => $tenant->owner_name]))
            ->line(__('notifications.void_checks.intro'));

        foreach ($this->counts as $kind => $count) {
            $mail->line(trans_choice("notifications.void_checks.kinds.{$kind}", $count, ['count' => $count]));
        }

        return $mail->line(__('notifications.void_checks.where'))->line(OwnerNoticeText::contactSentence());
    }

    private function total(): int
    {
        return array_sum($this->counts);
    }
}
