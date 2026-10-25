{{-- Where to listen for live updates: public values only (Reverb's public key, host, channel); the device token stays in PHP. --}}
<div wire:poll.15s @if ($realtime) data-vendapos-realtime="{{ $realtime }}" @endif>
    @if ($identity)
        <div class="flex items-center justify-between gap-4 border-b border-line bg-panel px-6 py-2 text-sm">
            <span class="flex items-center gap-2" role="status">
                <span @class([
                    'inline-block size-2.5',
                    'bg-ok' => $status->connection === \App\Sync\SyncStatus::ONLINE,
                    'bg-danger' => $status->connection === \App\Sync\SyncStatus::OFFLINE,
                    'bg-muted' => $status->connection === \App\Sync\SyncStatus::NEVER,
                ])></span>
                {{ __("terminal.status.{$status->connection}") }}
                @if ($status->lastSuccessAt)
                    <span class="text-muted">· {{ __('terminal.status.last_sync', ['time' => $status->lastSuccessAt->setTimezone($timezone)->format('H:i')]) }}</span>
                @endif
            </span>
            <span class="text-muted">{{ $identity->device_name }} · {{ $identity->branch_name }}</span>
        </div>

        @if ($status->accountSuspended)
            <p class="border-b border-danger px-6 py-2 text-sm text-danger" role="alert">{{ __('terminal.status.suspended') }}</p>
        @endif
    @endif
</div>
