<?php

namespace App\Actions;

use App\Models\DemoRequest;
use App\Notifications\DemoRequestReceived;
use App\Notifications\NewDemoRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SubmitDemoRequest
{
    /**
     * Stores the lead and queues the emails once the row is committed.
     * Idempotent on the form's request ID: a resubmission returns the
     * existing lead and sends nothing new.
     *
     * @param  array<string, mixed>  $data  Validated form input.
     */
    public function handle(array $data, string $locale): DemoRequest
    {
        return DB::transaction(function () use ($data, $locale) {
            $existing = DemoRequest::query()->whereKey($data['request_id'])->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            $lead = DemoRequest::create([
                'id' => $data['request_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'outlets' => $data['outlets'],
                'contact_window' => $data['contact_window'] ?? null,
                'message' => $data['message'] ?? null,
                'plan_interest' => $data['plan_interest'] ?? null,
                'locale' => $locale,
                'consented_at' => now(),
            ]);

            // Both notifications are queued with afterCommit, so a rollback sends nothing.
            $lead->notify(new DemoRequestReceived($lead));
            Notification::route('mail', config('marketing.leads.inbox'))->notify(new NewDemoRequest($lead));

            return $lead;
        });
    }
}
