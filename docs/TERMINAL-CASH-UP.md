# Cash-up on the till

How a till's cash drawer is opened, used and closed, for the people who run
it, and what the owner sees afterwards. The design is in
`docs/ARCHITECTURE.md` ("Cash-up (drawer shifts)").

## The rules in one place

| What | Who | Manager's PIN? |
| --- | --- | --- |
| Open a shift (count the float in) | anyone who takes payments | no |
| Sell | anyone who takes payments, only while a shift is open | no |
| Pay-out (cash spent on the business, with what it was for) | anyone who takes payments | yes, every time |
| Drop to the safe | anyone who takes payments | yes, every time |
| X report (the shift so far) | managers and owners only | no (they are signed in) |
| Close a shift (blind count) | anyone who takes payments | only when the count is off by more than the allowance |
| Void a cash sale | as before (manager's PIN) | the cash given back comes out of this till's open shift |

"Manager" means anyone whose role has **close shifts** (`shifts.close`):
managers and owners by default.

The **allowance** is how far a count may be off before a manager has to
approve it: KES 100 unless the owner changes it on **Cash-ups → Allowed difference**
in the owner panel. Tills pick up a change at their next sync.

## A day on the till

1. **Open.** Tap **Drawer**. Count the float into the drawer note by note:
   type how many of each note and coin. The total adds up as you go. Tap
   **Open shift**. Until a shift is open the till shows "No shift open"
   and **Pay** stays off. Waiters can still send orders to the kitchen.
2. **Sell** as usual. Every sale is written against the open shift.
3. **Pay-out or drop.** **Drawer → Pay-out** (say what it was for, e.g.
   "Charcoal from Mama Njeri") or **Drawer → Drop to safe**. Enter the
   amount, then a manager picks their name and enters their PIN. The till
   prints a slip and opens the drawer: the slip goes with the cash. The
   till refuses more than the drawer should hold.
4. **X report** (managers): **Drawer → X report** shows the float, cash
   sales, cash out and the expected cash so far, and can print it. The
   drawer stays shut.
5. **Close.** **Drawer → Close shift**. Count everything in the drawer note
   by note. The till does not show what it expects until the count is in.
   - Within the allowance: the shift closes and the **Z report** prints.
   - Off by more: the till shows how much short or over. The cashier can
     **count again** once. If it is still off, a manager writes what
     happened and approves with their PIN, and the Z report prints.
6. **Handover.** After a close, **Open the next shift** lets the next
   cashier count their float in. The drawer is one shift at a time.

Counting takes as long as it takes: the till does not sign the cashier out
while the Count screen is open.

## The Z report

Printed at close, and again from the closed screen if needed:

- the shift number (`T2-Z0007`: till code, then the till's own count)
- opened by and when, closed by and when
- float, cash sales, pay-outs, drops, void refunds
- **expected in drawer**, **counted**, and **short / over / exact**
- M-Pesa taken (for reference: it is not in the drawer), sales and voids
- every pay-out, drop and refund with its time and reason
- the closing count, note by note
- who approved the difference, and their note

Expected cash is always worked out the same way, on the till and on the
server:

    float + cash sales − pay-outs − drops − void refunds

Cash sales are what customers handed over less change. A sale voided later
still counts as cash taken; its refund is a separate line going out.

## Offline

Everything above works with no internet. The till keeps the shift and
sends it before the sales that name it once it is back online.

If the server ever refuses a shift (for example two tills claiming the same
shift number after a bad restore), the till's status bar says so on every
screen. Selling carries on. The owner sorts it out from the owner panel;
the till never resends it on its own.

## What the owner sees

- **Cash-ups**: every shift of every branch (managers: their branches),
  with its expected cash, count and difference. Open one for its Z detail.
  The owner sets the allowance here; each change is logged.
- **Cash to check**: what needs a second look:
  - a count off by more than the allowance
  - the till's and the server's figures differing
  - a close, pay-out or drop approved by someone who may not close shifts
  - a shift left open for more than a day
  - a sale made outside a shift
  
  The first two clear themselves when a late sale puts the figures right.
  A person marks the rest checked, with a note.
- An SMS/email digest when new items appear there, at most every half hour.

## For developers

- Till: `App\CashUp\{Drawer, Count, OpenShift, TakeCashOut, CloseShift,
  ShiftReport}`, `App\Livewire\CashUp` (`/cash-up`), `App\Sync\SyncShifts`
  (runs before `SyncOrders` in `SyncTill` and `PushOrdersJob`), print jobs
  `PrintCashSlipJob` and `PrintShiftReportJob`.
- Server: `App\Domain\CashUp` (`RecordShift`, `ReconcileShift`,
  `FlagShift`, `FlagStaleShifts`), `POST /api/v1/shifts/sync`,
  `cash-up:check`.
- Shared: `VendaPOS\Core\CashUp\ShiftCash`, `ShiftData`, `CashCountData`,
  `CashMovementData`, `KesDenomination`, `CashMovementKind`.
