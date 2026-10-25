<?php

declare(strict_types=1);

namespace App\Domain\Etims\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One failed attempt to send an eTIMS document.
 *
 * @property int $id
 * @property string $submission_id
 * @property int $attempt
 * @property string $error_code
 * @property string $error_message
 * @property Carbon $failed_at
 * @property Carbon|null $resolved_at
 */
class EtimsFailure extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'failed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
