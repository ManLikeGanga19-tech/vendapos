<?php

declare(strict_types=1);

namespace App\Infrastructure\Webhooks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One provider callback exactly as it arrived, logged before it is trusted.
 *
 * @property int $id
 * @property string $provider
 * @property string|null $token_hash
 * @property string|null $tenant_id
 * @property string|null $payment_id
 * @property string $ip
 * @property string $body
 * @property string $status
 * @property string|null $note
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
final class WebhookReceipt extends Model
{
    use CentralConnection;

    public const string RECEIVED = 'received';

    public const string PROCESSED = 'processed';

    public const string REJECTED = 'rejected';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function reject(string $note): void
    {
        $this->update(['status' => self::REJECTED, 'note' => $note, 'processed_at' => now()]);
    }

    public function markProcessed(?string $note = null): void
    {
        $this->update(['status' => self::PROCESSED, 'note' => $note, 'processed_at' => now()]);
    }
}
