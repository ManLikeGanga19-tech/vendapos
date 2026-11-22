<?php

declare(strict_types=1);

namespace App\Domain\CashUp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One till drawer's shift. The till's figures (till_expected_cents) sit beside
 * the server's own (expected_cents and its parts), which are worked out from
 * the synced sales and movements. Lives in the tenant's schema.
 *
 * @property string $id
 * @property string $branch_id
 * @property string $device_id
 * @property int $number Z report number, per till
 * @property Carbon $opened_at
 * @property string $opened_by
 * @property array<string, int> $opening_counts
 * @property int $float_cents
 * @property Carbon|null $closed_at
 * @property string|null $closed_by
 * @property array<string, int>|null $closing_counts
 * @property int|null $counted_cents
 * @property int|null $till_expected_cents
 * @property string|null $approved_by
 * @property string|null $note
 * @property int|null $cash_taken_cents
 * @property int|null $pay_outs_cents
 * @property int|null $drops_cents
 * @property int|null $void_refunds_cents
 * @property int|null $expected_cents
 * @property int|null $variance_cents counted minus the server's expected: below zero is short
 * @property Carbon|null $reconciled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Shift extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'opening_counts' => 'array',
            'closing_counts' => 'array',
            'float_cents' => 'integer',
            'counted_cents' => 'integer',
            'till_expected_cents' => 'integer',
            'cash_taken_cents' => 'integer',
            'pay_outs_cents' => 'integer',
            'drops_cents' => 'integer',
            'void_refunds_cents' => 'integer',
            'expected_cents' => 'integer',
            'variance_cents' => 'integer',
        ];
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** @return HasMany<CashMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('moved_at');
    }
}
