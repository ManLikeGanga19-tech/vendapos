<?php

namespace App\Models;

use App\Enums\ContactWindow;
use App\Enums\OutletRange;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use VendaPOS\Core\Enums\BusinessType;

/**
 * A "book a demo" lead from the public site.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $business_name
 * @property BusinessType $business_type
 * @property OutletRange $outlets
 * @property ContactWindow|null $contact_window
 * @property string|null $message
 * @property string $locale
 * @property string $status
 * @property Carbon $consented_at
 * @property Carbon $created_at
 */
class DemoRequest extends Model implements HasLocalePreference
{
    use HasUuids, Notifiable;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'business_name',
        'business_type',
        'outlets',
        'contact_window',
        'message',
        'locale',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'outlets' => OutletRange::class,
            'contact_window' => ContactWindow::class,
            'consented_at' => 'datetime',
        ];
    }

    /** Acknowledgement emails go out in the language the visitor used. */
    public function preferredLocale(): string
    {
        return $this->locale;
    }
}
