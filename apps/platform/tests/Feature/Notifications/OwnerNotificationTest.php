<?php

declare(strict_types=1);

use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Notifications\NotificationDelivery;
use App\Infrastructure\Notifications\OwnerNotification;
use App\Infrastructure\Notifications\SmsChannel;
use App\Infrastructure\Sms\FakeSmsClient;
use App\Infrastructure\Sms\SmsClient;
use App\Infrastructure\Sms\SmsResult;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

/** A minimal owner notice; its text is a translated string, so the language is visible. */
final class TestOwnerNotice extends OwnerNotification
{
    public function key(): string
    {
        return 'test.notice';
    }

    public function toSms(Tenant $tenant): string
    {
        return 'VendaPOS: '.__('owner.dashboard.branch');
    }

    public function toMail(Tenant $tenant): MailMessage
    {
        return (new MailMessage)->subject(__('owner.dashboard.branch'))->line('Test');
    }
}

beforeEach(function () {
    config(['vendapos.sms.client' => 'fake', 'mail.default' => 'array']);
    $this->sms = app(SmsClient::class);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function ownerTenant(array $attributes = []): Tenant
{
    return Tenant::create([
        'id' => 'towner-'.Str::lower(Str::random(6)),
        'name' => 'Duka la Mama',
        'owner_name' => 'Amina Said',
        'owner_phone' => '+254712345678',
        'owner_email' => 'amina@example.com',
        ...$attributes,
    ]);
}

it('texts and emails the owner and records both deliveries', function () {
    $tenant = ownerTenant();

    $tenant->notify(new TestOwnerNotice);

    expect($this->sms)->toBeInstanceOf(FakeSmsClient::class)
        ->and($this->sms->sent())->toBe([['to' => '+254712345678', 'message' => 'VendaPOS: Branch']]);
    expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1);

    $rows = NotificationDelivery::query()->orderBy('channel')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('channel')->all())->toBe(['mail', 'sms'])
        ->and($rows->pluck('recipient')->all())->toBe(['amina@example.com', '+254712345678'])
        ->and($rows->pluck('status')->unique()->all())->toBe([NotificationDelivery::SENT])
        ->and($rows->pluck('tenant_id')->unique()->all())->toBe([$tenant->id])
        ->and($rows->pluck('notification')->unique()->all())->toBe(['test.notice'])
        ->and($rows->every(fn (NotificationDelivery $row) => filled($row->provider_message_id)))->toBeTrue();
});

it('writes in the owner’s language', function () {
    ownerTenant(['locale' => 'sw'])->notify(new TestOwnerNotice);

    expect($this->sms->sent()[0]['message'])->toBe('VendaPOS: Tawi')
        ->and(app()->getLocale())->toBe('en'); // the app's own locale is left alone
});

it('only texts an owner without an email address', function () {
    ownerTenant(['owner_email' => null])->notify(new TestOwnerNotice);

    expect(NotificationDelivery::query()->pluck('channel')->all())->toBe(['sms'])
        ->and(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
});

it('records a provider’s refusal', function () {
    app()->instance(SmsClient::class, new class implements SmsClient
    {
        public function send(string $to, string $message): SmsResult
        {
            return SmsResult::refused('InvalidPhoneNumber (403)');
        }
    });

    ownerTenant(['owner_email' => null])->notify(new TestOwnerNotice);

    expect(NotificationDelivery::query()->sole())
        ->status->toBe(NotificationDelivery::REFUSED)
        ->error->toBe('InvalidPhoneNumber (403)')
        ->provider_message_id->toBeNull();
});

it('queues one job per channel on the notifications queue', function () {
    Queue::fake();

    ownerTenant()->notify(new TestOwnerNotice);

    Queue::assertPushed(SendQueuedNotifications::class, 2);
    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job) => $job->queue === 'notifications');
});

it('sends only after the change that caused it commits, and never if it rolls back', function () {
    $tenant = ownerTenant();

    DB::transaction(function () use ($tenant) {
        $tenant->notify(new TestOwnerNotice);
        expect($this->sms->sent())->toBe([]); // held until commit
    });
    expect($this->sms->sent())->toHaveCount(1);

    try {
        DB::transaction(function () use ($tenant) {
            $tenant->notify(new TestOwnerNotice);
            throw new RuntimeException('change failed');
        });
    } catch (RuntimeException) {
    }

    expect($this->sms->sent())->toHaveCount(1);
});

it('sends nothing to an owner without a phone, and records nothing', function () {
    $tenant = ownerTenant(['owner_email' => null, 'owner_phone' => null]);

    Notification::sendNow($tenant, new TestOwnerNotice, [SmsChannel::class]);

    expect($this->sms->sent())->toBe([])
        ->and(NotificationDelivery::query()->count())->toBe(0);
});
