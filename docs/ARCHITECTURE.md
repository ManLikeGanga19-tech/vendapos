# VendaPOS — Full System Architecture (Laravel + Filament Edition)

> Oct 20, 2026 · @Daniel Orwenjo
>
> Converted from `VendaPOS_Architecture_Laravel_Filament.pdf` v2.0.
> Sections marked **[unclear]** could not be fully converted (e.g. diagrams).

---

## 1 Executive summary

VendaPOS v2.0 replaces the Go / FastAPI / Kafka / Next.js / React Native stack with one Laravel modular monolith, Filament admin panels and NativePHP device apps, so every line of code the team writes is PHP. The load target is unchanged: 1M+ transactions per week is about 1.7 tx/s on average and about 83 tx/s at a 5,000/min dinner peak, which a small pool of Laravel Octane nodes handles with headroom.

One platform serves every kind of counter: restaurants, night clubs and bars, spas, salons and kinyozis, shops and dukas, supermarkets, hardware shops, chemists, liquor stores and other service or retail businesses. Each branch switches on a vertical pack (section 3.4) on top of the same sale, payment, eTIMS and sync core.

The move trades Kafka's replayable event log and Go's raw concurrency for one codebase, one deploy and one hiring profile. Replayability is kept with an append-only domain-event table; concurrency is covered by Octane (FrankenPHP or Swoole) and horizontal pods.

| Dimension | v1.0 (March 2026) | v2.0 (Laravel + Filament) |
|---|---|---|
| Backend | Go microservices + FastAPI | Laravel 12+ modular monolith on Octane |
| Admin / owner UI | Next.js 14 dashboard | Filament 4+ panels (Admin, Owner, KDS) |
| POS terminal | Electron + React + better-sqlite3 | NativePHP Desktop (Laravel + Livewire, local SQLite) |
| Mobile | React Native + Expo | NativePHP Mobile, or Owner panel as a PWA |
| Async backbone | Redpanda (Kafka API) | Laravel events + queues on Redis, run by Horizon |
| Real-time | Gorilla WebSocket hub | Laravel Reverb (Pusher protocol) + Echo |
| Multi-tenancy | Schema-per-tenant, hand-rolled | stancl/tenancy, PostgreSQL schema-per-tenant |
| Analytics | ClickHouse via Kafka sink | Postgres read replica + summary tables; ClickHouse later |
| Shared code | @vendapos/shared (TS + Zod) | vendapos/core Composer package (DTOs, enums, rules) |
| Compliance | eTIMS OSCU/VSCU, Daraja, Pesapal/DPO | Unchanged, re-implemented as Laravel jobs |

"Pure PHP" means the code we author. NativePHP still runs on Electron underneath, and Livewire ships its own small JavaScript runtime; neither needs a JS developer to maintain.

---

## 2 Architecture philosophy & overview

VendaPOS v2.0 is a modular monolith: one Laravel codebase and one image, with the order and its outbox event committed to Postgres before the terminal gets a 201, and everything else run asynchronously on queues.

- **Durable write first:** Postgres replaces Kafka as the first durable stop; the outbox keeps every mutation as an immutable, replayable event.
- **Offline-first terminal:** every terminal works fully offline and syncs on reconnect.
- **Tenant isolation at every layer:** subdomain, device token, Postgres schema, and tenant context inside every queued job.
- **Fewer moving parts:** no service-to-service HTTP, so no service mesh to run.

**[unclear]** Architecture diagram (dashed border = third-party systems, reached only from Horizon jobs, never from a request) was a visual in the PDF and could not be converted to text.

### Transaction hot path

1. Terminal saves the order to local SQLite and shows it as placed.
2. The sync job posts a batch; the API validates it and commits order + outbox in one transaction, then returns 201 (target ~10-40 ms, approximate).
3. Horizon runs inventory, eTIMS and receipt jobs for the new order.
4. Reverb pushes the KRA-stamped receipt (FDN + QR) back to the terminal, target about 1-2 s end to end.

---

## 3 Client layer

Four clients, all Laravel: the POS terminal and mobile app are NativePHP builds, while the owner dashboard, super-admin and kitchen display are Filament panels served by the central app.

### 3.1 POS terminal (NativePHP Desktop)

The terminal is a Laravel app packaged with NativePHP for the dedicated Linux touch-screen machine. Its UI is Livewire 3 + Tailwind with the existing palette: `#0F0A1E` surfaces, `#F4622A` primary actions, white text, 48x48px minimum tap targets.

- **Local database:** SQLite inside the app's storage path; every order is written locally first.
- **Background work:** NativePHP runs a queue worker and the scheduler in-process, so the SyncManager is a scheduled `SyncPendingOrders` job, not a Node loop.
- **Hardware:** ESC/POS printing through `mike42/escpos-php` over USB or network; cash drawer kick via the printer.
- **Kiosk:** NativePHP window config sets fullscreen + kiosk; devtools off in production builds.
- **Why not Filament here:** Filament is built for forms and tables; a fast touch item grid, numpad and split-bill flow are better as custom Livewire components that reuse Filament's Tailwind tokens.

```php
// app/Providers/NativeAppServiceProvider.php (terminal app)
public function boot(): void
{
    Window::open()
        ->fullscreen()
        ->kiosk()
        ->showDevTools(! app()->isProduction());
}
```

### 3.2 Filament panels (central app)

Owner dashboards use Filament stats and chart widgets fed from summary tables (section 7), never from live OLTP queries.

| Panel | Domain | Users | Main resources |
|---|---|---|---|
| Admin | admin.vendapos.com | VendaPOS staff | Tenants, plans, feature flags, devices, eTIMS certification status, DLQ |
| Owner | {tenant}.vendapos.com | Owners, managers | Menu + HS codes, staff + PINs, inventory, branches, devices, reports |
| KDS | {tenant}.vendapos.com/kds | Kitchen | Live ticket board over Reverb, bump/recall |

### 3.3 Mobile app

Two options, decided by licence and offline needs: NativePHP Mobile (same Livewire screens, local SQLite, push) for waiters who need offline order-taking, or the Owner panel installed as a PWA for owners who only read reports. Business rules live in `vendapos/core`, so both share validation and pricing logic with the terminal.

### 3.4 One terminal, many business types

Every branch picks a business type at setup; that switches on a vertical pack: feature flags (Laravel Pennant, scoped per branch), a terminal screen layout, and starter catalogue templates. Selling, payments, eTIMS and offline sync are the same code for all of them.

| Business type | Terminal mode | Features switched on | Typical hardware |
|---|---|---|---|
| Restaurant, cafe, hotel kitchen | Table service | Floor plan and tables, courses, modifiers, KDS tickets, split bill, tips | Touch screen, kitchen printer, KDS |
| Night club, bar, lounge | Tabs | Open tabs per customer or table, bottle service, happy-hour pricing, cover charge at the door, void and "staff drink" controls | Touch screen, receipt printer |
| Spa, salon, kinyozi | Appointments | Booking calendar, timed services, stylist or barber per service, commission per staff, packages, M-Pesa deposits | Tablet or touch screen |
| Shop, duka, kiosk | Quick sale | Barcode scan, quick keys, customer credit book (deni) | Scanner, receipt printer |
| Supermarket, minimart | Multi-lane retail | Barcode + weighing-scale PLU codes, promotions and bundles, cashier lanes, shift cash-up, loyalty | Scanner, scale, cash drawer, customer display |
| Hardware shop | Retail + quotes | Units of measure (piece, metre, kg, bag), quotation to invoice, delivery notes, contractor pricing, credit accounts | Scanner, A4 printer |
| Chemist, pharmacy | Regulated retail | Batch and expiry tracking (first-expiry-first-out), prescription records, controlled-items register, pharmacist sign-off, insurance billing | Scanner, receipt printer |
| Liquor store | Licensed retail | Age-check prompt, sale-hours lock per licence, case and bulk pricing, excise-aware items | Scanner, receipt printer |
| Any other business | General | Generic products and services, custom fields | Any of the above |

Packs are per branch, not per tenant, so one business can mix them: a hotel runs table service in the restaurant, tabs at the bar and appointments at the spa under one owner panel and one KRA PIN.

```php
// packages/core/src/Enums/BusinessType.php
enum BusinessType: string
{
    case Restaurant = 'restaurant';
    case Bar = 'bar';
    case Salon = 'salon';        // spa, salon, kinyozi
    case Shop = 'shop';
    case Supermarket = 'supermarket';
    case Hardware = 'hardware';
    case Pharmacy = 'pharmacy';
    case Liquor = 'liquor';
    case General = 'general';

    public function terminalMode(): TerminalMode
    {
        return match ($this) {
            self::Restaurant => TerminalMode::TableService,
            self::Bar => TerminalMode::Tabs,
            self::Salon => TerminalMode::Appointments,
            self::Supermarket => TerminalMode::MultiLane,
            default => TerminalMode::QuickSale,
        };
    }
}
```

---

## 4 Project structure

One Git monorepo holds two Laravel apps and one shared Composer package; `vendapos/core` replaces `@vendapos/shared` as the single source of truth for DTOs, enums, validation rules, tax and pricing math.

```
vendapos/
├── apps/
│   ├── platform/                 # Central Laravel app (API + Filament + workers)
│   │   ├── app/
│   │   │   ├── Domain/           # Modules — no cross-module Eloquent calls
│   │   │   │   ├── Orders/       # Actions, Models, Events, Jobs, Policies
│   │   │   │   ├── Payments/     # Daraja, Pesapal, DPO, Flutterwave drivers
│   │   │   │   ├── Inventory/
│   │   │   │   ├── Etims/        # OSCU client, VSCU batch, DLQ
│   │   │   │   ├── Receipts/     # PDF (spatie/laravel-pdf), R2 upload
│   │   │   │   ├── Reporting/    # Summary-table builders
│   │   │   │   ├── Verticals/    # Hospitality, Tabs, Appointments, Retail, Hardware, Pharmacy, Liquor packs
│   │   │   │   └── Tenancy/      # Tenant, Branch, Device, Plan
│   │   │   ├── Filament/
│   │   │   │   ├── Admin/        # Super-admin panel
│   │   │   │   ├── Owner/        # Tenant panel
│   │   │   │   └── Kds/          # Kitchen display panel
│   │   │   └── Http/Api/V1/      # Terminal + mobile JSON API
│   │   ├── database/migrations/
│   │   │   ├── central/          # tenants, domains, plans, devices
│   │   │   └── tenant/           # orders, payments, items… per schema
│   │   └── routes/{api,tenant,channels}.php
│   └── pos-terminal/             # NativePHP Desktop (+ Mobile build target)
│       ├── app/Livewire/         # OrderScreen, PaymentScreen, ItemGrid, NumPad
│       ├── app/Jobs/SyncPendingOrders.php
│       └── app/Printing/         # ESC/POS drivers
├── packages/
│   └── core/                     # vendapos/core (path repository)
│       ├── src/Data/             # spatie/laravel-data DTOs: OrderData, PaymentData
│       ├── src/Enums/            # PaymentType, OrderStatus, VatCategory
│       ├── src/Tax/              # VAT 16% / 0% / exempt calculators
│       └── lang/{en,sw}/         # English + Swahili strings
├── infra/{helm,terraform}/
└── .github/workflows/
```

Module boundaries are enforced by Deptrac or Pest architecture tests, so the monolith can be split later without a rewrite.

---

## 5 HTTP & API layer

The Go gateway becomes Laravel middleware running on Octane: the framework boots once per worker, so a sync request costs roughly 10-40 ms including the Postgres write (approximate, to be confirmed by load test).

| Concern | v2.0 implementation |
|---|---|
| Runtime | Laravel Octane on FrankenPHP (or Swoole), 1 worker per vCPU |
| Auth (terminal, mobile) | Sanctum personal access tokens, one per device, abilities per role |
| Auth (panels) | Filament session auth + staff PIN login on terminals |
| Tenant resolution | stancl/tenancy by subdomain for panels; tenant from the token's device record for API — never from the body |
| Device binding | `X-Device-ID` must match `personal_access_tokens.device_id`, else 401 + audit |
| Rate limiting | `RateLimiter::for()` on Redis: 200/min per device, 5,000/min per tenant |
| Validation | Form Requests built from `vendapos/core` rules; unknown keys rejected; 64 KB body cap |
| Idempotency | Client-generated order UUID + unique index; replays return the stored response |

```php
// app/Http/Api/V1/Middleware/EnsureDeviceBinding.php
public function handle(Request $request, Closure $next): Response
{
    $token = $request->user()->currentAccessToken();
    if ($token->device_id !== $request->header('X-Device-ID')) {
        AuditLog::record('token_device_mismatch', $token->device_id);
        abort(401);
    }
    tenancy()->initialize($token->device->tenant_id);
    return $next($request);
}
```

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'device.bound', 'throttle:device', 'throttle:tenant'])
    ->prefix('v1')->group(function () {
        Route::post('orders/sync', SyncOrdersController::class);   // batch of up to 50
        Route::post('payments/mpesa/stk', InitiateStkPushController::class);
    });
```

---

## 6 Async backbone — events, queues, Horizon

Redpanda is replaced by a transactional outbox in Postgres plus Laravel queues on Redis: the order and its `domain_events` row commit in one transaction, then queued listeners do inventory, eTIMS, receipts and analytics.

### 6.1 Queue design (replaces topics)

| Queue | Replaces topic | Workers (Horizon) | Jobs |
|---|---|---|---|
| payments | pos.payments | 4-12, auto-balance | ConfirmPayment, QueryStkStatus |
| etims | pos.etims | 4-8 | SubmitSaleToEtims |
| etims-retry | pos.etims.dlq | 1 | RetryFailedEtims (backoff 30s -> 60s -> 120s -> hourly) |
| inventory | pos.inventory | 2-8 | DecrementStock |
| receipts | pos.receipts | 4-12 | GenerateReceiptPdf, BroadcastReceiptReady |
| notifications | pos.notifications | 2 | SMS, push, email |
| analytics | cg-analytics-sink | 2-4 | UpdateSalesSummaries |

### 6.2 Ordering & idempotency

- **Per-tenant ordering:** jobs that must run in order per tenant use `WithoutOverlapping("tenant:{id}")` or `ShouldBeUnique` keyed by tenant — the Kafka partition-key guarantee, without partitions.
- **Noisy tenants:** Horizon `balance=auto` plus a per-tenant job rate limiter stops one tenant's dinner rush from starving others.
- **Exactly-once effects:** every job carries the event UUID; a `processed_events` unique index makes reruns no-ops.
- **Failures:** `$tries`, `backoff()` and `failed()` per job; failed eTIMS jobs land in `etims_failures`, visible and retryable from the Admin panel.

### 6.3 Replay and audit

`domain_events` is append-only (no UPDATE/DELETE grants for the app role) and holds the same envelope as v1.0 — event_id, event_type, tenant_id, branch_id, device_id, occurred_at, schema_ver, payload. Replaying a tenant means re-dispatching its rows in occurred_at order.

```php
// app/Domain/Orders/Actions/RecordOrder.php
DB::transaction(function () use ($data) {
    $order = Order::create($data->toModelAttributes());
    $order->lines()->createMany($data->lines->toArray());
    DomainEvent::record('order.created', $order);   // outbox row
});
// Dispatched after commit — never lost, never sent for a rolled-back order
OrderCreated::dispatch($order)->afterCommit();
```

---

## 7 Data layer

PostgreSQL 16 stays the system of record with one schema per tenant managed by stancl/tenancy; Redis keeps cache, sessions, rate limits and queues; ClickHouse moves from day-one to a later phase.

### 7.1 PostgreSQL — schema-per-tenant

- **central schema:** tenants, domains, plans, devices, Sanctum tokens, `domain_events`.
- One `tenant_{uuid}` schema per business; stancl/tenancy's PostgreSQL schema manager creates it, runs `database/migrations/tenant`, and sets `search_path` on every request and queued job.
- Queued jobs are tenant-aware automatically (the tenant id travels in the job payload), which replaces `SET LOCAL app.tenant_id`.
- RLS stays as a last-resort guard on the few shared tables that carry `tenant_id` (`domain_events`, `devices`).

```php
// config/tenancy.php
'database' => [
    'managers' => [
        'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
    ],
],
```

### 7.2 Redis

| Use | Laravel feature | TTL / note |
|---|---|---|
| Menu & config cache | `Cache::tags(['tenant:{id}', 'menu'])` | 5 min, flushed by model observers |
| Sessions | Filament / web sessions | Panel logins |
| Token revocation | Sanctum token delete + cache bust | Instant logout |
| Rate limits | RateLimiter | Per device + per tenant |
| Queues | Horizon | Separate Redis DB from cache |
| Real-time | Reverb horizontal scaling over Redis pub/sub | KDS + receipt-ready |

### 7.3 Analytics

Phase 1: `UpdateSalesSummaries` maintains hourly and daily rollups (`sales_hourly`, `item_sales_daily`) per tenant; Filament widgets and reports read them from a read replica. Phase 2, when rollups exceed what Postgres serves in under 200 ms: a queue sink writes events to ClickHouse and the Reporting module switches its query driver.

### 7.4 Terminal SQLite store

Each terminal keeps orders, menu snapshot and an `offline_queue` table in local SQLite. NativePHP does not ship SQLCipher, so protection is LUKS full-disk encryption plus Laravel encrypted casts on sensitive columns, with the app key held in the OS keychain.

### 7.5 Catalogue model for many verticals

One `items` table (type: product, service, bundle or modifier) carries the eTIMS item classification and tax category for every business; vertical behaviour lives in optional tables that only the packs that need them use.

| Table | Used by | Holds |
|---|---|---|
| item_variants | Shops, supermarkets, salons | Size, colour, service length |
| item_components | Restaurants, bars, salons | Recipes and products a service consumes, so stock falls correctly |
| stock_batches | Pharmacies, supermarkets, liquor stores | Batch number, expiry, quantity, cost |
| item_units | Hardware, supermarkets | Base unit and conversions (metre, kg, bag, case) |
| tables, tabs | Restaurants, bars, clubs | Floor plan, open tabs and their running totals |
| appointments, staff_services, commissions | Spas, salons, kinyozis | Bookings, who does what, earnings per staff |
| customer_accounts | Shops, hardware | Credit limits and balances |
| prescriptions, controlled_register | Pharmacies | Prescriber, patient reference, controlled-item log |

Because tax data sits on `items`, the eTIMS job never branches on business type.

---

## 8 Payment integrations

Each provider is a driver behind one `PaymentGateway` contract in the Payments module, so adding Airtel Money or a new card acquirer is a new class, not a new service.

| Method | Provider | Hardware | Confirmation in v2.0 |
|---|---|---|---|
| Card swipe/tap | Pesapal / DPO | PDQ terminal | IPN webhook, then server-side status query |
| M-Pesa | Safaricom Daraja | Customer phone | Callback + STK query fallback |
| Airtel Money | Flutterwave / iPay | Customer phone | Webhook signature (provider-supplied secret hash) |
| Cash | Local only | ESC/POS drawer | Recorded on terminal, synced |
| Split | Mixed | PDQ + phone | One payments row per tender, order paid when sum = total |

### 8.1 M-Pesa Daraja STK Push

```php
// app/Domain/Payments/Drivers/DarajaDriver.php
public function stkPush(Order $order, string $phone): StkRequest
{
    $timestamp = now('Africa/Nairobi')->format('YmdHis');
    $response = Http::withToken($this->accessToken())          // cached ~55 min
        ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => base64_encode($this->shortcode.$this->passkey.$timestamp),
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerBuyGoodsOnline',
            'Amount'            => (int) ceil($order->total),
            'PartyA'            => $phone,
            'PartyB'            => $this->till,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => route('webhooks.mpesa', ['token' => $this->callbackToken]),
            'AccountReference'  => $order->reference,
            'TransactionDesc'   => 'VendaPOS sale',
        ])->throw();
    return StkRequest::fromDaraja($order, $response->json());
}
```

Correction to v1.0: Daraja STK callbacks are not HMAC-signed, so the v1.0 `Verify()` check would reject every real callback. v2.0 trusts a callback only when all three hold: a secret token in the callback URL, a Safaricom source-IP allowlist, and a matching CheckoutRequestID on a pending request. A `QueryStkStatus` job confirms any payment whose callback is late or missing.

### 8.2 Card, Airtel Money, cash

VendaPOS never handles raw card data, so PCI-DSS scope stays with Pesapal/DPO.

---

## 9 KRA eTIMS integration

The FastAPI worker becomes the Laravel Etims module: a `SubmitSaleToEtims` job per completed sale on the `etims` queue, with the same field mapping as v1.0 (taxpayer PIN, branch ID, device serial, HS code, VAT rate, payment type) and the FDN + QR stored on the order.

```php
// app/Domain/Etims/Jobs/SubmitSaleToEtims.php
class SubmitSaleToEtims implements ShouldQueue, ShouldBeUnique
{
    public int $tries = 4;
    public function backoff(): array { return [30, 60, 120]; }
    public function uniqueId(): string { return $this->order->uuid; }

    public function handle(OscuClient $oscu): void
    {
        $response = $oscu->postSale([
            'tin'      => tenant('kra_pin'),
            'bhfId'    => $this->order->branch->etims_branch_id,
            'sarNo'    => $this->order->sequence_number,
            'pmtTyCd'  => $this->order->etimsPaymentCode(),   // CASH | MPESA | CARD
            'taxblAmtB'=> $this->order->taxable_amount,
            'taxAmtB'  => $this->order->vat_amount,
            'totAmt'   => $this->order->total,
            'itemList' => $this->order->lines->map->toEtimsItem()->all(),
        ]);
        $this->order->etimsReceipt()->create($response->only(['fdn', 'qr', 'invoice_id']));
        GenerateReceiptPdf::dispatch($this->order)->onQueue('receipts');
    }

    public function failed(Throwable $e): void
    {
        EtimsFailure::record($this->order, $e);   // retried by RetryFailedEtims, visible in Admin panel
    }
}
```

Offline sales sync with their original timestamps and sequence numbers, then go to the VSCU batch path (up to 10,000 per batch, as in v1.0). Open question: confirm the exact OSCU vs VSCU endpoint rules and batch limits against KRA's current integration spec before certification.

---

## 10 Security architecture

The six defence layers from v1.0 stay; each is re-implemented with Laravel and Filament primitives. With no service-to-service HTTP inside a monolith, mTLS between services is no longer needed.

| Layer | v1.0 control | v2.0 control |
|---|---|---|
| L1 Device | Electron sandbox, kiosk, SQLCipher | NativePHP kiosk window, dedicated posuser account, systemd restart, LUKS + encrypted casts, signed auto-updates |
| L2 Auth | JWT RS256, 15-min TTL, device-bound | Sanctum device tokens (expiring, revocable), staff PIN switch on terminal, 2FA on Filament panels |
| L2 RBAC | Role claim | spatie/laravel-permission + Filament Shield: waiter, cashier, manager, owner, platform-admin |
| L3 Edge | Rate limit, Zod, tenant isolation | Throttle middleware, Form Requests, tenancy middleware, Cloudflare WAF |
| L4 Data | TLS 1.3, mTLS, RLS, Vault | TLS 1.3 at ingress and to Postgres/Redis, schema isolation + RLS on shared tables, secrets via Vault or Doppler into env |
| L5 Audit | Kafka audit log, anomaly rules | Append-only `domain_events` + `audit_logs` (owen-it/laravel-auditing), anomaly checks as scheduled jobs |
| L6 Network | K8s NetworkPolicy | Same default-deny policies, fewer pods to describe (section 11) |

Anomaly rules carry over unchanged: void rate above 10%, orders between 23:00 and 06:00, and more than 50 orders per minute from one device each raise an alert and an Admin-panel flag.

```php
// app/Domain/Tenancy/Actions/IssueDeviceToken.php — tokens are issued per device, never per user
$token = $device->createToken(
    name: "terminal:{$device->id}",
    abilities: config('vendapos.device_abilities'), // orders:sync, payments:initiate, staff:read
    expiresAt: now()->addHours(12),
);
```

The token belongs to the till, not to whoever is using it, because staff switch by PIN while the till may be offline and cannot wait for a new token. The till pulls its branch's staff list (`GET /api/v1/staff`: names, roles, permissions and PIN hashes, never PINs) and checks PINs and permissions locally. Each order records who made it and who approved any void or discount, so the server can check after sync.

**Voids.** Only a whole completed sale is voided, with a reason from a fixed list (`VoidReason`; "other" needs a note) and a manager's or owner's PIN; a manager may approve their own void. The till that made the sale sends the void with the order (`status: voided` plus a `void` block of who voided it, who approved it, why and when), so it works offline. Another till of the same branch voids it online with `POST /api/v1/orders/{id}/void`; sales of other branches answer 404. The server stores the void in `order_voids` and never refuses one for its approver or its day — the money has already gone back over the counter — it records whether the approver could approve voids at that branch and whether the void fell on the sale's business day (Africa/Nairobi), for the owner to check.

---

## 11 Infrastructure & deployment

One Docker image (FrankenPHP + the platform app) runs as four Kubernetes Deployments that differ only by command; Postgres and Redis move to managed services, so the cluster holds no stateful pods.

| Deployment | Command | Replicas (HPA) | Scales on |
|---|---|---|---|
| web | `php artisan octane:start --server=frankenphp` | 2-10 | CPU 60% |
| horizon | `php artisan horizon` | 2-8 | Queue wait time (Horizon metrics) |
| reverb | `php artisan reverb:start` | 2-4 | Connections |
| scheduler | `php artisan schedule:work` | 1 | — |

```yaml
# infra/helm/vendapos/templates/horizon.yaml (excerpt)
containers:
  - name: horizon
    image: ghcr.io/vendapos/platform:{{ .Values.image.tag }}
    command: ["php", "artisan", "horizon"]
    lifecycle:
      preStop:
        exec: { command: ["php", "artisan", "horizon:terminate"] }
terminationGracePeriodSeconds: 120   # let in-flight eTIMS jobs finish
```

The v1.0 default-deny NetworkPolicy pattern is kept per namespace. Simpler alternatives for an early launch: Laravel Cloud or Forge-managed VMs, with the same four process types.

| Namespace | Contains | Egress allowed to |
|---|---|---|
| pos-app | web, horizon, reverb, scheduler | Managed Postgres, Redis, R2, KRA, Daraja, Pesapal/DPO |
| pos-infra | Prometheus, Grafana, Loki | Internal + Grafana Cloud |
| ingress | Nginx ingress, TLS 1.3 | pos-app only |

---

## 12 Offline-first & sync

The terminal behaves exactly as in v1.0 — write locally, sync in the background — but the SyncManager is now a PHP job sending batches of 50 to `POST /v1/orders/sync`, with the order UUID as the idempotency key.

| State | Behaviour |
|---|---|
| Online | Order saved to SQLite, synced within ~2 s |
| Brief disconnect | Queue grows; retries with backoff 30 s -> 60 s -> 120 s, capped at 1 h |
| Extended offline | On reconnect, server routes the backlog to the VSCU batch path |
| Payments offline | Cash and PDQ cards only; M-Pesa needs connectivity |
| Conflicts | Server wins for stock; last-write-wins for order status |

```php
// apps/pos-terminal/app/Jobs/SyncPendingOrders.php — scheduled every 2 s via NativePHP scheduler
public function handle(PlatformApi $api): void
{
    $batch = OfflineQueue::due()->oldest()->limit(50)->get();
    if ($batch->isEmpty()) return;

    try {
        $result = $api->syncOrders($batch->pluck('payload')->all());
        OfflineQueue::whereIn('uuid', $result->accepted)->update(['status' => 'synced']);
    } catch (ConnectionException) {
        $batch->each(fn ($row) => $row->scheduleRetry(
            min(30 * 2 ** $row->retry_count, 3600)
        ));
    }
}
```

The server replies per UUID (accepted, duplicate, rejected with reason), so one bad order never blocks the other 49.

---

## 13 Observability & CI/CD

Laravel's first-party tools cover most of what v1.0 built by hand; Prometheus, Grafana, Loki and PagerDuty stay for alerting.

| Tool | Purpose | Key signals |
|---|---|---|
| Laravel Pulse | Live app health | Slow requests, slow queries, queue throughput, per-tenant usage |
| Horizon | Queue dashboard | Wait time, failed jobs, etims-retry depth |
| spatie/laravel-prometheus | Metrics export | tx/s, p95 latency, error rate, queue wait |
| OpenTelemetry PHP | Tracing | Terminal sync -> controller -> job -> eTIMS |
| Loki | Logs | Structured JSON logs from all pods |
| Sentry or Flare | Exceptions | Grouped errors with tenant + device context |
| PagerDuty | On-call | eTIMS failures > 2%, retry backlog > 0 for 2 min, p95 > 100 ms |

### CI/CD pipeline

| Stage | Tool | Action |
|---|---|---|
| Style + static analysis | Pint, Larastan (level 8), Rector | Every PR; blocks merge |
| Tests | Pest + architecture tests, coverage > 80% | Postgres 16 + Redis as GitHub Actions services |
| Build | Docker buildx | linux/amd64 for K8s, linux/arm64 for Pi terminals |
| Push | GitHub Container Registry | Git SHA + semver tags |
| Deploy dev | `helm upgrade --install` | Auto on merge to `development`; runs central + tenant migrations |
| Deploy staging | `helm upgrade --install` | Auto on merge to `staging` (PR from `development`) |
| Deploy prod | Manual approval | On merge to `production` (PR from `staging`); GitHub Environment protection |
| Terminal release | `php artisan native:build linux` | Signed .deb, auto-update channel |
| Mobile release | NativePHP Mobile build | Store release on approval |

---

## 14 Stack summary, trade-offs & migration

The whole platform now runs on PHP 8.3+, Laravel, Filament, Livewire and NativePHP, on top of the same Postgres, Redis, Cloudflare and Kubernetes foundations as v1.0.

| Layer | Technology |
|---|---|
| POS terminal | NativePHP Desktop + Livewire + Tailwind, local SQLite |
| Admin, owner, KDS | Filament panels |
| Mobile | NativePHP Mobile or Owner-panel PWA |
| API + domain | Laravel modular monolith on Octane (FrankenPHP) |
| Async | Laravel queues + Horizon on Redis, Postgres outbox |
| Real-time | Laravel Reverb + Echo |
| Tenancy | stancl/tenancy, Postgres schema-per-tenant |
| Auth + RBAC | Sanctum device tokens, spatie/laravel-permission, Filament Shield |
| OLTP / cache | PostgreSQL 16 + read replica / Redis 7 |
| Analytics | Summary tables now; ClickHouse in phase 2 |
| Files | Cloudflare R2 via Laravel filesystem (S3 driver) |
| PDFs | spatie/laravel-pdf |
| Observability | Pulse, Horizon, Prometheus, Grafana, Loki, OpenTelemetry, Sentry/Flare |
| CI/CD | GitHub Actions, Pint, Larastan, Pest, Helm |

### What v2.0 gives up, and the mitigation

- **Kafka replay and fan-out:** covered by `domain_events` replay; revisit Kafka only if more than three independent consumers need the full stream.
- **Go-level concurrency per pod:** Octane removes framework boot cost; the remaining gap is solved with more pods, which the 83 tx/s peak does not stress.
- **Sub-second analytics on billions of rows:** not needed until tenant count grows; ClickHouse slots in behind the Reporting module.
- **Custom touch UI in React:** Livewire components need care for snappy touch input — use `wire:model.live` sparingly and Alpine for instant local feedback.

### Migration order from v1.0

1. Build `vendapos/core` and the central app with tenancy, Sanctum and the Admin panel.
2. Port orders, payments and eTIMS modules; run Pest tests against the v1.0 payload fixtures.
3. Ship the Owner and KDS panels, then the NativePHP terminal to one pilot restaurant.
4. Cut over tenants in batches; retire Go services and Redpanda once the outbox has processed a full week cleanly.

### Open questions

- NativePHP Mobile licence terms and production readiness for offline waiter devices.
- Load-test target: confirm p95 under 100 ms at 5,000 orders/min on the chosen node size.
- KRA OSCU/VSCU spec details (section 9).
- Excise duty handling for liquor items in eTIMS, and whether excise stamp checks apply at retail.
- Pharmacy and Poisons Board record-keeping rules for prescriptions and controlled items.
- Liquor licence trading hours per county, to drive the sale-hours lock.
