<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * A menu category, copied from the server's catalogue.
 *
 * @property string $id
 * @property string $name
 * @property int $sort_order
 */
#[Table(name: 'categories', keyType: 'string', incrementing: false, timestamps: false)]
#[Unguarded]
final class Category extends Model {}
