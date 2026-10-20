# CLAUDE.md — VendaPOS standing rules

Read this file fully at the start of every session. These rules apply to every task unless
Daniel overrides one explicitly in the current session.

## 1. What we are building

Multi-tenant SaaS POS for Kenya / East Africa: restaurants, clubs and bars, spas, salons,
kinyozis, shops, supermarkets, hardware, chemists, liquor stores and more. One sale /
payment / eTIMS / sync core; per-branch **vertical packs** switch on extra behaviour.

Architecture source of truth: `docs/ARCHITECTURE.md` (converted from `docs/ARCHITECTURE.pdf`).
If code and the architecture doc disagree, raise it — don't pick one silently.

## 2. Stack (verify current stable versions before installing; never assume from memory)

| Area | Choice |
| --- | --- |
| Language | PHP 8.3+ only for code we author |
| Platform app | Laravel modular monolith on Octane (FrankenPHP) |
| Admin / Owner / KDS | Filament panels |
| Terminal & mobile | NativePHP (Desktop; Mobile later) + Livewire + Tailwind |
| Tenancy | stancl/tenancy, PostgreSQL **schema-per-tenant** |
| Async | Laravel queues + Horizon on Redis; Postgres outbox (`domain_events`) |
| Real-time | Laravel Reverb + Echo |
| Auth / RBAC | Sanctum device tokens; spatie/laravel-permission + Filament Shield |
| Feature flags | Laravel Pennant, scoped per branch |
| DTOs | spatie/laravel-data in `packages/core` |
| Tests / quality | Pest (+ arch tests), Larastan level 8, Pint, Rector |

Do not add Go, Python, Node services, Kafka, React or Vue. Node is allowed only as Vite's
build tool.

## 3. Architecture non-negotiables

1. **Tenant isolation everywhere.** Tenant comes from the subdomain (panels) or the device
   token's device record (API) — never from the request body. Every queued job must be
   tenant-aware. Every feature touching tenant data needs a cross-tenant leak test.
2. **Durable write first.** Order + lines + `domain_events` outbox row commit in one DB
   transaction; side effects (inventory, eTIMS, receipts, analytics) run in queued jobs
   dispatched `afterCommit()`.
3. **Idempotency.** Client-generated UUIDs on orders and payments with unique indexes; jobs
   carry the event UUID and are no-ops on rerun (`processed_events`).
4. **Modules don't reach into each other.** `app/Domain/<Module>` talks to others via
   Actions, events or contracts — never another module's Eloquent models. Arch tests enforce it.
5. **Offline-first terminal.** Terminal writes SQLite first, syncs in batches of ≤ 50.
6. **Vertical packs are additive.** Business-type behaviour lives in `app/Domain/Verticals`
   and optional tables; the core sale and eTIMS paths never branch on business type.

## 4. Domain rules that are easy to get wrong

- **Money:** integer cents in KES via the `Money` value object. Never floats. Round only at
  presentation and at M-Pesa `Amount` (whole shillings).
- **Time:** store UTC; display and compute business days in `Africa/Nairobi`.
- **VAT:** 16% standard, 0% zero-rated, exempt — from `VatCategory`, never hard-coded.
- **M-Pesa Daraja:** STK callbacks are **not** HMAC-signed. Verify with a secret token in the
  callback URL + Safaricom IP allowlist + matching `CheckoutRequestID`, and confirm late or
  missing callbacks with an STK status query.
- **eTIMS:** follow KRA's current OSCU/VSCU spec. If a field or rule is uncertain, stop and ask.
- **Languages:** all user-facing strings via `lang/en` and `lang/sw`.

## 5. How to work

1. Plan before code for anything over ~30 lines or touching more than one module. Wait for
   approval when Daniel is present.
2. Small, reviewable diffs. One concern per commit.
3. Tests first or alongside: Pest unit tests for `packages/core`, feature tests for actions
   and endpoints, a leak test for anything tenant-scoped.
4. Run Pint, Larastan and Pest for the affected app before every commit. Never commit red.
5. When you're unsure of a package API or a regulation, say so and check the official docs
   instead of guessing.

## 6. Git and GitHub — absolute rules

- Commits are authored only by the identity in `git config user.name` / `user.email`.
  Never change it; never pass `--author`.
- **No AI attribution anywhere:** no `Co-Authored-By:` trailers, no `Claude-Session:` trailers,
  no "Generated with Claude Code", no robot emoji — not in commits, PR titles or bodies,
  code comments, changelogs or release notes.
- Never use `--no-verify` / `-n` or otherwise skip hooks. `.githooks/commit-msg` strips
  attribution as a backstop; CI rejects PRs that still contain it.
- Conventional Commits: `feat(orders): …`, `fix(etims): …`, `test(tenancy): …`, `chore(ci): …`.
- Feature branches only (`feat/…`, `fix/…`). Never push to `main`. Never force-push.
- Before every push, confirm this prints nothing:
  `git log origin/main..HEAD --format='%B' | grep -iE 'co-authored-by|claude|anthropic|generated with'`
- PR body sections: Summary, Changes, How to test, Risks.

## 7. Security

- Never read, print or commit `.env` files, keys or tokens. Use `.env.example` with placeholders.
- Webhooks: verify before trusting; log the raw body; respond fast and process in a job.
- Validate every API input with Form Requests built from `packages/core` rules; reject unknown keys.
- Rate limits: 200/min per device, 5,000/min per tenant.

## 8. Stop and ask Daniel before

- Changing money, tax, eTIMS, payment-callback or tenant-isolation logic beyond the task.
- Pinning a package below latest stable, adding a paid licence, or adding a new dependency
  not in §2.
- Any destructive command (dropping schemas, history rewrites, force-push, `rm -rf` outside
  build directories).

## 9. Definition of done

Code + tests green locally and in CI, Larastan clean, Pint clean, docs updated if behaviour
changed, PR opened from a feature branch with no AI attribution.
