<?php

declare(strict_types=1);

use App\Domain\Catalogue\Models\Item;
use App\Domain\Etims\Actions\TransmitEtimsDocument;
use App\Domain\Etims\Contracts\EtimsClient;
use App\Domain\Etims\Data\EtimsResult;
use App\Domain\Etims\Features\EtimsEnabled;
use App\Domain\Etims\Jobs\SendEtimsDocument;
use App\Domain\Etims\Jobs\SubmitSaleToEtims;
use App\Domain\Etims\Models\EtimsFailure;
use App\Domain\Etims\Models\EtimsSubmission;
use App\Domain\Orders\Models\Order;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Outbox\DomainEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Pennant\Feature;
use VendaPOS\Core\Data\EtimsDocumentData;
use VendaPOS\Core\Enums\EtimsDocumentType;
use VendaPOS\Core\Enums\EtimsStatus;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

uses(DatabaseMigrations::class);

/** Records every document and answers from a script; once the script runs out it accepts. */
final class ScriptedEtimsClient extends EtimsClient
{
    /** @var list<EtimsDocumentData> */
    public array $sent = [];

    /** @param list<EtimsResult|Throwable> $script */
    public function __construct(public array $script = []) {}

    public function submitSale(EtimsDocumentData $document): EtimsResult
    {
        return $this->answer($document);
    }

    public function submitCreditNote(EtimsDocumentData $document): EtimsResult
    {
        return $this->answer($document);
    }

    private function answer(EtimsDocumentData $document): EtimsResult
    {
        $this->sent[] = $document;
        $next = array_shift($this->script);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next ?? EtimsResult::accepted(['fdn' => 'FDN-'.count($this->sent), 'qr' => 'qr', 'invoice_id' => 'INV-'.count($this->sent)]);
    }
}

beforeEach(function () {
    [$this->tenant, $this->branch] = tenantWithBranch();
    $this->tenant->run(fn () => $this->branch->update(['etims_branch_id' => '00']));
    $this->enrolment = enrolFor($this->tenant, $this->branch);

    $this->nyama = $this->tenant->run(fn () => Item::create([
        'type' => ItemType::Product, 'name' => 'Nyama choma', 'price_cents' => 185_000,
        'vat_category' => VatCategory::Standard, 'etims' => ['item_class' => '5020230601'],
    ]));

    $this->client = new ScriptedEtimsClient;
    app()->instance(EtimsClient::class, $this->client);
});

afterEach(function () {
    tenancy()->end();
    Tenant::all()->each->delete();
});

function enableEtims(Branch $branch): void
{
    Feature::for(EtimsEnabled::scope($branch->id))->activate(EtimsEnabled::class);
}

function orderIn(Tenant $tenant, string $uuid): Order
{
    return $tenant->run(fn () => Order::query()->findOrFail($uuid));
}

function submissionIn(Tenant $tenant, string $orderId, EtimsDocumentType $type = EtimsDocumentType::Sale): EtimsSubmission
{
    return $tenant->run(fn () => EtimsSubmission::query()
        ->where('order_id', $orderId)->where('document_type', $type)->firstOrFail());
}

/** Read inside the tenant: parsing a stored timestamp needs the tenant connection. */
function secondsToNextAttempt(Tenant $tenant, string $orderId): ?float
{
    return $tenant->run(fn () => submissionIn($tenant, $orderId)->next_attempt_at?->diffInSeconds(now(), true));
}

/** @return list<array{attempt: int, code: string, resolved: bool}> */
function etimsFailuresIn(Tenant $tenant): array
{
    return $tenant->run(fn () => EtimsFailure::query()->orderBy('id')->get()
        ->map(fn (EtimsFailure $f) => ['attempt' => $f->attempt, 'code' => $f->error_code, 'resolved' => $f->resolved_at !== null])
        ->all());
}

it('marks every order "not required" while the branch has eTIMS off, and sends nothing', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    expect(orderIn($this->tenant, $sale['uuid'])->etims_status)->toBe(EtimsStatus::NotRequired)
        ->and(submissionIn($this->tenant, $sale['uuid'])->status)->toBe(EtimsStatus::NotRequired)
        ->and($this->client->sent)->toBe([]);
});

it('sends the sale after commit and stores the receipt on the order', function () {
    enableEtims($this->branch);
    $sale = saleOf([[$this->nyama, 2]], sequence: 7);

    sync([$sale]);

    $order = orderIn($this->tenant, $sale['uuid']);
    expect($order->etims_status)->toBe(EtimsStatus::Submitted)
        ->and($order->etims_receipt)->toEqual(['fdn' => 'FDN-1', 'qr' => 'qr', 'invoice_id' => 'INV-1'])
        ->and($this->client->sent)->toHaveCount(1);

    $document = $this->client->sent[0];
    expect($document->document_type)->toBe(EtimsDocumentType::Sale)
        ->and($document->etims_branch_id)->toBe('00')
        ->and($document->device_id)->toBe($this->enrolment->device->id)
        ->and($document->sequence_number)->toBe(7)
        ->and($document->total_cents)->toBe(370_000)
        ->and($document->vat_cents)->toBe(51_034)
        ->and($document->lines->all()[0]->vat_category)->toBe(VatCategory::Standard)
        ->and($document->lines->all()[0]->unit_price_cents)->toBe(185_000)
        ->and($document->lines->all()[0]->item_codes)->toBe(['item_class' => '5020230601']);
});

it('keeps a failed sale pending and retries it at 30 s, 60 s and 120 s, then hourly', function () {
    enableEtims($this->branch);
    $this->client->script = array_fill(0, 4, EtimsResult::rejected('kra_down', 'Service unavailable'));
    $sale = saleOf([[$this->nyama, 1]]);

    $this->freezeSecond();
    sync([$sale]);

    $nextAttempts = [secondsToNextAttempt($this->tenant, $sale['uuid'])];

    foreach ([30, 60, 120] as $wait) {
        $this->travel($wait)->seconds();
        $this->artisan('etims:retry')->assertSuccessful();
        $nextAttempts[] = secondsToNextAttempt($this->tenant, $sale['uuid']);
    }

    expect($nextAttempts)->toBe([30.0, 60.0, 120.0, 3600.0])
        ->and(orderIn($this->tenant, $sale['uuid'])->etims_status)->toBe(EtimsStatus::Pending)
        ->and(array_column(etimsFailuresIn($this->tenant), 'attempt'))->toBe([1, 2, 3, 4]);

    $this->travel(3600)->seconds();
    $this->artisan('etims:retry')->assertSuccessful();

    expect(orderIn($this->tenant, $sale['uuid'])->etims_status)->toBe(EtimsStatus::Submitted)
        ->and(collect(etimsFailuresIn($this->tenant))->every(fn (array $f) => $f['resolved']))->toBeTrue();
});

it('does not resend before the retry is due', function () {
    enableEtims($this->branch);
    $this->client->script = [EtimsResult::rejected('kra_down', 'Service unavailable')];
    $sale = saleOf([[$this->nyama, 1]]);

    sync([$sale]);
    $this->travel(10)->seconds();
    $this->artisan('etims:retry')->assertSuccessful();
    (new SendEtimsDocument($this->tenant->id, submissionIn($this->tenant, $sale['uuid'])->id))->handle(app(TransmitEtimsDocument::class));

    expect($this->client->sent)->toHaveCount(1);
});

it('counts a client exception as a failed attempt', function () {
    enableEtims($this->branch);
    $this->client->script = [new RuntimeException('Connection timed out')];
    $sale = saleOf([[$this->nyama, 1]]);

    sync([$sale]);

    expect(etimsFailuresIn($this->tenant))->toBe([['attempt' => 1, 'code' => 'client_error', 'resolved' => false]])
        ->and(submissionIn($this->tenant, $sale['uuid'])->status)->toBe(EtimsStatus::Pending);
});

it('gives up after the last attempt and marks the order failed', function () {
    config(['vendapos.etims.max_attempts' => 2]);
    enableEtims($this->branch);
    $this->client->script = array_fill(0, 2, EtimsResult::rejected('invalid_item', 'Unknown item class'));
    $sale = saleOf([[$this->nyama, 1]]);

    sync([$sale]);
    $this->travel(31)->seconds();
    $this->artisan('etims:retry')->assertSuccessful();
    $this->travel(1)->hours();
    $this->artisan('etims:retry')->assertSuccessful();

    expect(orderIn($this->tenant, $sale['uuid'])->etims_status)->toBe(EtimsStatus::Failed)
        ->and(secondsToNextAttempt($this->tenant, $sale['uuid']))->toBeNull()
        ->and($this->client->sent)->toHaveCount(2);
});

it('fails, never "sends", when a branch is switched on before a real client exists', function () {
    app()->forgetInstance(EtimsClient::class);
    enableEtims($this->branch);
    $sale = saleOf([[$this->nyama, 1]]);

    sync([$sale]);

    expect(orderIn($this->tenant, $sale['uuid'])->etims_status)->toBe(EtimsStatus::Pending)
        ->and(etimsFailuresIn($this->tenant))->toBe([['attempt' => 1, 'code' => 'no_client', 'resolved' => false]]);
});

it('sends a credit note carrying the sale’s receipt when an order is voided', function () {
    enableEtims($this->branch);
    $sale = saleOf([[$this->nyama, 1]]);

    sync([$sale]);
    sync([[...$sale, 'status' => 'voided']])->assertJsonPath('results.0.status', 'updated');

    $creditNote = submissionIn($this->tenant, $sale['uuid'], EtimsDocumentType::CreditNote);
    expect($creditNote->status)->toBe(EtimsStatus::Submitted)
        ->and($this->client->sent)->toHaveCount(2)
        ->and($this->client->sent[1]->document_type)->toBe(EtimsDocumentType::CreditNote)
        ->and($this->client->sent[1]->original_receipt)->toEqual(['fdn' => 'FDN-1', 'qr' => 'qr', 'invoice_id' => 'INV-1'])
        // The order keeps the sale's receipt; the credit note's lives on its submission.
        ->and(orderIn($this->tenant, $sale['uuid'])->etims_receipt['fdn'])->toBe('FDN-1');
});

it('needs no credit note for a sale that was never reported', function () {
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);

    enableEtims($this->branch); // switched on between the sale and its void
    sync([[...$sale, 'status' => 'voided']]);

    expect(submissionIn($this->tenant, $sale['uuid'], EtimsDocumentType::CreditNote)->status)->toBe(EtimsStatus::NotRequired)
        ->and($this->client->sent)->toBe([]);
});

it('holds a credit note back until its sale has been accepted', function () {
    enableEtims($this->branch);
    $this->client->script = [EtimsResult::rejected('kra_down', 'Service unavailable')];
    $sale = saleOf([[$this->nyama, 1]]);

    sync([$sale]);
    sync([[...$sale, 'status' => 'voided']]);

    expect(submissionIn($this->tenant, $sale['uuid'], EtimsDocumentType::CreditNote)->status)->toBe(EtimsStatus::Pending)
        ->and(array_column(etimsFailuresIn($this->tenant), 'code'))->toBe(['kra_down', 'sale_not_submitted'])
        ->and($this->client->sent)->toHaveCount(1);

    $this->travel(31)->seconds();
    $this->artisan('etims:retry')->assertSuccessful();

    expect(submissionIn($this->tenant, $sale['uuid'])->status)->toBe(EtimsStatus::Submitted)
        ->and(submissionIn($this->tenant, $sale['uuid'], EtimsDocumentType::CreditNote)->status)->toBe(EtimsStatus::Submitted)
        ->and(array_map(fn (EtimsDocumentData $d) => $d->document_type, $this->client->sent))
        ->toBe([EtimsDocumentType::Sale, EtimsDocumentType::Sale, EtimsDocumentType::CreditNote]);
});

it('reports and reverses an order that was rung up and voided while offline', function () {
    enableEtims($this->branch);
    $sale = saleOf([[$this->nyama, 1]], overrides: ['status' => 'voided']);

    sync([$sale])->assertJsonPath('results.0.status', 'accepted');

    expect(submissionIn($this->tenant, $sale['uuid'])->status)->toBe(EtimsStatus::Submitted)
        ->and(submissionIn($this->tenant, $sale['uuid'], EtimsDocumentType::CreditNote)->status)->toBe(EtimsStatus::Submitted);
});

it('is a no-op when the event job runs again', function () {
    enableEtims($this->branch);
    $sale = saleOf([[$this->nyama, 1]]);
    sync([$sale]);

    $eventId = $this->tenant->run(fn () => DomainEvent::query()->where('aggregate_id', $sale['uuid'])->value('event_id'));
    (new SubmitSaleToEtims($this->tenant->id, $eventId))->handle();

    expect($this->tenant->run(fn () => EtimsSubmission::query()->count()))->toBe(1)
        ->and($this->client->sent)->toHaveCount(1);
});

it('never sends one tenant’s document from another tenant, and flags stay per branch', function () {
    enableEtims($this->branch);
    $sale = saleOf([[$this->nyama, 1]]);
    $this->client->script = [EtimsResult::rejected('kra_down', 'Service unavailable')];
    sync([$sale]);
    $submissionId = submissionIn($this->tenant, $sale['uuid'])->id;

    [$other, $otherBranch] = tenantWithBranch();
    $this->travel(31)->seconds();
    (new SendEtimsDocument($other->id, $submissionId))->handle(app(TransmitEtimsDocument::class));

    expect($this->client->sent)->toHaveCount(1)
        ->and(submissionIn($this->tenant, $sale['uuid'])->status)->toBe(EtimsStatus::Pending)
        ->and($other->run(fn () => EtimsSubmission::query()->count()))->toBe(0)
        ->and(Feature::for(EtimsEnabled::scope($otherBranch->id))->active(EtimsEnabled::class))->toBeFalse();
});
