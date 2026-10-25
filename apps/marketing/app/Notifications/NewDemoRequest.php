<?php

namespace App\Notifications;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerts the sales inbox to a new lead. Always in English. */
class NewDemoRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DemoRequest $lead)
    {
        $this->afterCommit();
        $this->locale('en');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;

        return (new MailMessage)
            ->subject(__('demo.mail.sales.subject', ['business' => $lead->business_name]))
            ->replyTo($lead->email, $lead->name)
            ->line(__('demo.mail.sales.intro'))
            ->line(__('demo.fields.name').': '.$lead->name)
            ->line(__('demo.fields.business_name').': '.$lead->business_name)
            ->line(__('demo.fields.business_type').': '.__('demo.business_types.'.$lead->business_type->value))
            ->line(__('demo.fields.outlets').': '.__('demo.outlets.'.$lead->outlets->value))
            ->line(__('demo.fields.plan_interest').': '.($lead->plan_interest ? __("plans.{$lead->plan_interest}.name") : '—'))
            ->line(__('demo.fields.email').': '.$lead->email)
            ->line(__('demo.fields.phone').': '.$lead->phone)
            ->line(__('demo.fields.contact_window').': '.($lead->contact_window ? __('demo.contact_windows.'.$lead->contact_window->value) : '—'))
            ->line(__('demo.mail.sales.language').': '.config("marketing.locales.{$lead->locale}.label"))
            ->line(__('demo.fields.message').': '.($lead->message ?? '—'))
            ->line(__('demo.mail.sales.reference').': '.$lead->id);
    }
}
