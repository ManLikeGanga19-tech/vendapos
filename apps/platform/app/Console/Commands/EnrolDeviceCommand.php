<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Tenancy\Actions\EnrolDevice;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('devices:enrol {tenant : Tenant ID} {branch : Branch UUID in that tenant} {name : Label, e.g. "Till 1"}')]
#[Description('Enrol a POS device for a tenant branch and print its API token once')]
final class EnrolDeviceCommand extends Command
{
    public function handle(EnrolDevice $enrol): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (! $tenant instanceof Tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        /** @var string $branch */
        $branch = $this->argument('branch');
        /** @var string $name */
        $name = $this->argument('name');

        try {
            $enrolment = $enrol->handle($tenant, $branch, $name);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Device {$enrolment->device->id} enrolled.");
        $this->line('Token (shown once, store it on the terminal): '.$enrolment->plainTextToken);

        return self::SUCCESS;
    }
}
