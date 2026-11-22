<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Notifications;

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Notifications\OwnerNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * One digest of new M-Pesa money to check (typed codes not confirmed, amounts
 * that differ, codes used twice, paid pushes with no sale), at most every
 * half hour so the owner is not sent one SMS per code.
 */
final class MpesaChecksNotice extends OwnerNotification
{
    /** @param array<string, int> $counts kind (MpesaCheck::KINDS) => how many are new */
    public function __construct(public readonly array $counts) {}

    public function key(): string
    {
        return 'mpesa.checks';
    }

    public function toSms(Tenant $tenant): string
    {
        return trans_choice('notifications.mpesa_checks.sms', $this->total(), ['business' => $tenant->name, 'count' => $this->total()]);
    }

    public function toMail(Tenant $tenant): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(trans_choice('notifications.mpesa_checks.subject', $this->total(), ['business' => $tenant->name, 'count' => $this->total()]))
            ->greeting(__('notifications.greeting', ['name' => $tenant->owner_name]))
            ->line(__('notifications.mpesa_checks.intro'));

        foreach ($this->counts as $kind => $count) {
            $mail->line(trans_choice("notifications.mpesa_checks.kinds.{$kind}", $count, ['count' => $count]));
        }

        return $mail->line(__('notifications.mpesa_checks.where'))->line(OwnerNoticeText::contactSentence());
    }

    private function total(): int
    {
        return array_sum($this->counts);
    }
}
