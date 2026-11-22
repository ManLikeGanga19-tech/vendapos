<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Data\CashCountData;
use VendaPOS\Core\Enums\KesDenomination;

/**
 * One drawer shift on this till: opened with a counted float, closed with a
 * blind count. Written here first and synced after (VendaPOS\Core\Data\ShiftData).
 * At most one is open at a time.
 *
 * @property string $id
 * @property int $number
 * @property Carbon $opened_at
 * @property string $opened_by
 * @property array<int|string, int> $opening_counts
 * @property Carbon|null $closed_at
 * @property string|null $closed_by
 * @property array<int|string, int>|null $closing_counts
 * @property int|null $expected_cents
 * @property string|null $approved_by
 * @property string|null $note
 * @property string $sync_status
 * @property bool $close_synced
 * @property string|null $sync_reason
 * @property array<string, mixed>|null $sync_errors
 * @property Carbon|null $synced_at
 */
#[Table(name: 'shifts', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class Shift extends Model
{
    public const string PENDING = 'pending';

    public const string SYNCED = 'synced';

    public const string REJECTED = 'rejected';

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function openingFloat(): CashCountData
    {
        return new CashCountData($this->opening_counts);
    }

    public function closingCount(): ?CashCountData
    {
        return $this->closing_counts === null ? null : new CashCountData($this->closing_counts);
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('moved_at');
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Something the server does not have yet: the opening, a movement or the close.
     *
     * @param  Builder<Shift>  $query
     */
    public function scopeAwaitingSync(Builder $query): void
    {
        $query->where('sync_status', '!=', self::REJECTED)
            ->where(fn (Builder $q) => $q->where('sync_status', self::PENDING)
                ->orWhere(fn (Builder $closed) => $closed->whereNotNull('closed_at')->where('close_synced', false))
                ->orWhereHas('movements', fn (Builder $m) => $m->where('synced', false)))
            ->orderBy('number');
    }

    /**
     * As the server takes it (VendaPOS\Core\Data\ShiftData): the opening, the
     * given movements, and the close only when $withClose.
     *
     * @param  list<CashMovement>  $movements
     * @return array<string, mixed>
     */
    public function toSyncPayload(array $movements, bool $withClose): array
    {
        $payload = [
            'uuid' => $this->id,
            'number' => $this->number,
            'opened_at' => $this->opened_at->toIso8601String(),
            'opened_by' => $this->opened_by,
            'opening_float' => ['counts' => self::sendable($this->opening_counts)],
            'movements' => array_map(fn (CashMovement $movement) => $movement->toSyncPayload(), $movements),
        ];

        if (! $withClose || $this->closed_at === null) {
            return $payload;
        }

        return $payload + [
            'closed_at' => $this->closed_at->toIso8601String(),
            'closed_by' => $this->closed_by,
            'closing_count' => ['counts' => self::sendable($this->closing_counts ?? [])],
            'expected_cents' => $this->expected_cents,
            ...($this->approved_by !== null ? ['approved_by' => $this->approved_by, 'note' => $this->note] : []),
        ];
    }

    /**
     * Every denomination, zeros included: an empty drawer is still a count,
     * and the server refuses a count with nothing in it.
     *
     * @param  array<int|string, int>  $counts
     * @return array<int, int>
     */
    private static function sendable(array $counts): array
    {
        $count = new CashCountData($counts);
        $sendable = [];

        foreach (KesDenomination::cases() as $denomination) {
            $sendable[$denomination->value] = $count->countOf($denomination);
        }

        return $sendable;
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'opened_at' => 'datetime',
            'opening_counts' => 'array',
            'closed_at' => 'datetime',
            'closing_counts' => 'array',
            'expected_cents' => 'integer',
            'close_synced' => 'boolean',
            'sync_errors' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
