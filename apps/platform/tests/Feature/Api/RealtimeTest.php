<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Actions\ResetStaffPin;
use App\Domain\Tenancy\Actions\SaveStaff;
use App\Domain\Tenancy\Actions\SuspendTenant;
use App\Domain\Tenancy\Models\Staff;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Realtime\BroadcastTillSync;
use App\Infrastructure\Realtime\TillsShouldSync;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'vendapos.realtime.host' => 'realtime.vendapos.test',
        'vendapos.realtime.port' => 443,
        'vendapos.realtime.scheme' => 'https',
    ]);

    // Channels were registered at boot on the 'null' broadcaster; register them again on Reverb.
    app(BroadcastManager::class)->purge();
    require base_path('routes/channels.php');

    // Nothing here talks to a real Reverb server; signals are only recorded.
    Queue::fake([BroadcastTillSync::class]);

    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->till = enrolFor($this->tenant, $this->branch);
    [$this->other, $otherBranch] = tenantWithBranch();
    $this->otherTill = enrolFor($this->other, $otherBranch);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function joinChannel(string $token, string $deviceId, string $channelDeviceId): TestResponse
{
    app('auth')->forgetGuards();

    return test()->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-till.{$channelDeviceId}"], [
        'Authorization' => "Bearer {$token}",
        'X-Device-ID' => $deviceId,
    ]);
}

/**
 * Forget recorded signals. A faked queue never runs the jobs, so their
 * "already queued" locks stay until they expire: step past that first.
 * Faked outside the tenant, since ending tenancy swaps a fake made inside it.
 */
function freshSignals(): void
{
    test()->travel((new BroadcastTillSync([], TillsShouldSync::MENU))->uniqueFor + 1)->seconds();
    Queue::fake([BroadcastTillSync::class]);
}

/** @return list<array{what: string, devices: list<string>}> */
function signalsSent(): array
{
    return Queue::pushed(BroadcastTillSync::class)
        ->map(fn (BroadcastTillSync $job) => ['what' => $job->what, 'devices' => $job->deviceIds])
        ->values()
        ->all();
}

describe('joining a till channel', function () {
    it('lets a till join its own channel', function () {
        joinChannel($this->till->plainTextToken, $this->till->device->id, $this->till->device->id)
            ->assertOk()
            ->assertJsonStructure(['auth']);
    });

    it('refuses another till of the same business', function () {
        $second = enrolFor($this->tenant, $this->branch);

        joinChannel($this->till->plainTextToken, $this->till->device->id, $second->device->id)->assertForbidden();
    });

    it('refuses a till of another business (leak test)', function () {
        joinChannel($this->otherTill->plainTextToken, $this->otherTill->device->id, $this->till->device->id)->assertForbidden();
    });

    it('refuses without a device token, or once the till is revoked', function () {
        app('auth')->forgetGuards();
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-till.{$this->till->device->id}"])
            ->assertUnauthorized();

        $this->till->device->revoke();

        expect(joinChannel($this->till->plainTextToken, $this->till->device->id, $this->till->device->id)->status())->toBeIn([401, 403]);
    });
});

describe('what sends a signal', function () {
    // No signals or locks left by the setup above.
    beforeEach(fn () => freshSignals());

    it('tells every till of the business, and no other business, when the menu changes', function () {
        $second = enrolFor($this->tenant, $this->branch);

        $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Chai', 'price_cents' => 5_000, 'vat_category' => VatCategory::Standard]));

        expect(signalsSent())->toHaveCount(1)
            ->and(signalsSent()[0]['what'])->toBe(TillsShouldSync::MENU)
            ->and(signalsSent()[0]['devices'])->toEqualCanonicalizing([$this->till->device->id, $second->device->id]);
    });

    it('leaves revoked tills out of business-wide signals', function () {
        $revoked = enrolFor($this->tenant, $this->branch)->device;
        $revoked->revoke();
        freshSignals();

        $this->tenant->run(fn () => Item::create(['type' => ItemType::Product, 'name' => 'Chai', 'price_cents' => 5_000, 'vat_category' => VatCategory::Standard]));

        expect(signalsSent()[0]['devices'])->toBe([$this->till->device->id]);
    });

    it('tells tills when someone is added, changed or gets a new PIN', function () {
        $owner = $this->tenant->run(function () {
            $owner = Staff::create(['name' => 'Owner']);
            $owner->assignRole('owner');

            return $owner;
        });
        freshSignals();

        $cashierId = $this->tenant->run(fn () => app(SaveStaff::class)->handle($owner, null, 'Achieng', 'cashier', [$this->branch->id]));
        expect(signalsSent())->toBe([['what' => TillsShouldSync::STAFF, 'devices' => [$this->till->device->id]]]);

        freshSignals();
        $this->tenant->run(fn () => app(ResetStaffPin::class)->handle($owner, $cashierId));

        expect(signalsSent())->toBe([['what' => TillsShouldSync::STAFF, 'devices' => [$this->till->device->id]]]);
    });

    it('stays quiet for changes tills never see, like a panel login', function () {
        $owner = $this->tenant->run(fn () => Staff::create(['name' => 'Owner']));
        freshSignals();

        $this->tenant->run(fn () => $owner->forceFill(['last_login_at' => now()])->save());
        expect(signalsSent())->toBe([]);

        // And the same save with a till field changed does signal, so the quiet above is real.
        $this->tenant->run(fn () => $owner->forceFill(['name' => 'Owner Renamed'])->save());
        expect(signalsSent())->toHaveCount(1);
    });

    it('tells a till straight away that it was revoked, but not that it was seen', function () {
        $this->till->device->forceFill(['last_seen_at' => now()])->save();
        expect(signalsSent())->toBe([]);

        $this->till->device->revoke();

        expect(signalsSent())->toBe([['what' => TillsShouldSync::DEVICE, 'devices' => [$this->till->device->id]]]);
    });

    it('tells every till when the business is suspended', function () {
        app(SuspendTenant::class)->handle($this->tenant, 'Unpaid', User::factory()->create());

        expect(signalsSent())->toContain(['what' => TillsShouldSync::ACCOUNT, 'devices' => [$this->till->device->id]]);
    });
});

it('sends each signal on the till’s own private channel, with no data but what changed', function () {
    $event = new TillsShouldSync(['d1', 'd2'], TillsShouldSync::MENU);

    expect(array_map(fn ($channel) => $channel->name, $event->broadcastOn()))->toBe(['private-till.d1', 'private-till.d2'])
        ->and($event->broadcastAs())->toBe('till.sync')
        ->and($event->broadcastWith())->toBe(['what' => 'menu']);
});

it('tells a till where to listen', function () {
    deviceRequest('GET', '/api/v1/device', $this->till->plainTextToken, $this->till->device->id)
        ->assertOk()
        ->assertJsonPath('realtime', [
            'key' => 'test-key',
            'host' => 'realtime.vendapos.test',
            'port' => 443,
            'scheme' => 'https',
            'channel' => "till.{$this->till->device->id}",
            'event' => 'till.sync',
        ]);

    config(['broadcasting.default' => 'null']);

    deviceRequest('GET', '/api/v1/device', $this->till->plainTextToken, $this->till->device->id)->assertJsonPath('realtime', null);
});
