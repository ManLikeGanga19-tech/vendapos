<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

#[Signature('users:grant-admin {email : Email of an existing user}')]
#[Description('Give an existing user the platform-admin role, which opens the Admin panel')]
final class GrantAdminCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        Role::findOrCreate('platform-admin');
        $user->assignRole('platform-admin');

        $this->info("{$user->email} is now a platform admin.");

        return self::SUCCESS;
    }
}
