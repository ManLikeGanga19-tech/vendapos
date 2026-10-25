<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Device;
use Illuminate\Support\Facades\Broadcast;

/*
| Real-time channels. Each till listens on its own private channel and only
| ever hears "something changed, sync now"; the data itself comes through
| the normal API. Only the till itself, unrevoked, may join its channel.
*/
Broadcast::channel('till.{deviceId}', fn (mixed $user, string $deviceId) => $user instanceof Device
    && hash_equals($user->id, $deviceId)
    && ! $user->isRevoked());
