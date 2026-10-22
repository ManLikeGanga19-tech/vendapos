<?php

declare(strict_types=1);

namespace App\Domain\Etims\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\EtimsDocumentType;
use VendaPOS\Core\Enums\EtimsStatus;

/**
 * A document VendaPOS owes KRA eTIMS for one order. Lives in the tenant's schema.
 *
 * @property string $id
 * @property string $order_id
 * @property string $branch_id
 * @property EtimsDocumentType $document_type
 * @property EtimsStatus $status
 * @property array<string, mixed> $order_snapshot
 * @property array<string, mixed>|null $receipt
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $locked_until
 * @property Carbon|null $submitted_at
 */
class EtimsSubmission extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => EtimsDocumentType::class,
            'status' => EtimsStatus::class,
            'order_snapshot' => 'array',
            'receipt' => 'array',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
            'locked_until' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return HasMany<EtimsFailure, $this> */
    public function failures(): HasMany
    {
        return $this->hasMany(EtimsFailure::class, 'submission_id');
    }
}
