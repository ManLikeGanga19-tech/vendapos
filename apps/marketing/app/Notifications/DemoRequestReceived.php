<?php

namespace App\Notifications;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Acknowledges the request to the visitor, in the language they used. */
class DemoRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DemoRequest $lead)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('demo.mail.visitor.subject'))
            ->greeting(__('demo.mail.visitor.greeting', ['name' => $this->lead->name]))
            ->line(__('demo.mail.visitor.body', ['business' => $this->lead->business_name]))
            ->line(__('demo.mail.visitor.next'))
            ->salutation(__('demo.mail.visitor.signoff'));
    }
}
