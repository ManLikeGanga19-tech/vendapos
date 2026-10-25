<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Notifications;

use Carbon\CarbonInterface;

/** Shared wording for owner notices, in whatever locale the notice is being written in. */
final class OwnerNoticeText
{
    /** A date as the owner reads it: Nairobi calendar day, month name in their language. */
    public static function date(CarbonInterface $moment): string
    {
        return $moment->setTimezone((string) config('vendapos.business_timezone'))
            ->locale(app()->getLocale())
            ->translatedFormat('j F Y');
    }

    /** " Call 0700 000 000." when a support line is configured, otherwise nothing. */
    public static function contactSentence(): string
    {
        $phone = config('vendapos.support.phone');

        return filled($phone) ? ' '.__('notifications.contact', ['phone' => $phone]) : '';
    }
}
