<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * A side effect of one outbox event (architecture §6.2). The job carries the
 * tenant and event IDs, runs inside that tenant, and records itself in the
 * tenant's processed_events in the same transaction as its effect, so a rerun
 * or a duplicate delivery is a no-op.
 */
abstract class TenantEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    final public function __construct(
        public readonly string $tenantId,
        public readonly string $eventId,
    ) {
        $this->onQueue(static::queueName());
    }

    abstract protected static function queueName(): string;

    /** Applies the effect. Runs inside the tenant and inside the job's transaction. */
    abstract protected function process(DomainEvent $event): void;

    /** @return list<int> seconds before each retry */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    final public function handle(): void
    {
        // Purged since this was queued: nothing left to act on.
        if (! LiveTenants::has($this->tenantId)) {
            return;
        }

        $previous = tenant();
        tenancy()->initialize($this->tenantId);

        try {
            $event = DomainEvent::query()->findOrFail($this->eventId);

            // Never apply one tenant's event inside another tenant's schema.
            if ($event->tenant_id !== $this->tenantId) {
                throw new LogicException("Event {$this->eventId} does not belong to tenant {$this->tenantId}.");
            }

            DB::transaction(function () use ($event) {
                $claimed = DB::table('processed_events')->insertOrIgnore([
                    'event_id' => $event->event_id,
                    'handler' => static::class,
                    'processed_at' => now(),
                ]);

                if ($claimed === 1) {
                    $this->process($event);
                }
            });
        } finally {
            $previous === null ? tenancy()->end() : tenancy()->initialize($previous);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Domain event job failed.', [
            'job' => static::class,
            'tenant_id' => $this->tenantId,
            'event_id' => $this->eventId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
