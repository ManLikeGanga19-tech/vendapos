<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Infrastructure\Sms\SmsClient;
use App\Infrastructure\Sms\SmsResult;
use Illuminate\Notifications\Notification;
use LogicException;

/** Laravel notification channel for SMS: the text comes from toSms(), the number from routeNotificationForSms(). */
final class SmsChannel
{
    public function __construct(private readonly SmsClient $client) {}

    public function send(object $notifiable, Notification $notification): ?SmsResult
    {
        $to = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('sms', $notification) : null;

        if (! is_string($to) || $to === '') {
            return null;
        }

        if (! method_exists($notification, 'toSms')) {
            throw new LogicException($notification::class.' has no toSms().');
        }

        return $this->client->send($to, (string) $notification->toSms($notifiable));
    }
}
