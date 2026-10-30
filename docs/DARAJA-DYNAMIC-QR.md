# Daraja Dynamic QR: what we know and what we still check

Scan-to-pay on the bill (slice Q) uses Safaricom's Dynamic QR API. Safaricom's own
page for it (developer.safaricom.co.ke) would not load when this was written
(2026-10-30), so the facts below come from copies of the Daraja documentation and
community guides. Confirm each point on the official portal, or with a real call,
before going live.

## The call

`POST https://sandbox.safaricom.co.ke/mpesa/qrcode/v1/generate`; production is the
same path on `api.safaricom.co.ke`. It uses an OAuth bearer token, the same as the
STK push.

| Field | Meaning | What we send |
| --- | --- | --- |
| `MerchantName` | Name shown to the customer | The business name |
| `RefNo` | Transaction reference | A short reference for the bill, unique per payment |
| `Amount` | Amount to pay | Whole shillings (CLAUDE.md §4: M-Pesa amounts) |
| `TrxCode` | `BG` buy goods, `WA` agent withdrawal, `PB` paybill, `SM` send money, `SB` send to business | `PB` only |
| `CPI` | Credit party: the paybill, till or phone number | The account's paybill number |
| `Size` | Width and height of the square image, in pixels | Fits the 80 mm paper |

The response carries `ResponseCode`, `ResponseDescription` and `QRCode`. Guides say
`QRCode` is a base64 PNG, which the till prints as an image (PHP's GD extension;
NativePHP's bundled PHP has it on Windows and Linux).

## How we learn the customer paid

The documentation does not say. The payment is an ordinary paybill payment, so it
should arrive on the C2B confirmation URL we already register, with `BillRefNumber`
set to our `RefNo`. That is why scan-to-pay is for **paybill accounts only**
(Daniel, 2026-10-30): a buy-goods payment carries no account reference, so it can
only be matched on amount and time, which is unreliable. Buy-goods bills print the
till number as text, and the cashier takes the M-Pesa code as today.

## Still to confirm with a real call

1. `QRCode` really is a base64 PNG, not the raw QR text.
2. A scanned `PB` payment reaches our C2B confirmation URL with `BillRefNumber` equal
   to `RefNo`.
3. The limits on `RefNo` (length, characters) and on `Size`.
4. Whether the sandbox lets a scan be simulated, or only a production paybill can show it.
5. What a live paybill needs from Safaricom (Go Live, Dynamic QR approval).

## How VendaPOS uses it

- The till asks for a QR payment (`POST /api/v1/payments` with `"channel": "qr"` and no
  phone) when it prints a bill. The server refuses with `qr_needs_paybill` unless the
  branch's account is a paybill.
- The payment gets a reference such as `VPK7M2Q9TR` (no 0/O, 1/I/L or U). It is the
  `RefNo` in the QR and the account number printed under it.
- A C2B confirmation on the same account whose `BillRefNumber` is the reference (any
  case) pays it if the amount matches. A different amount leaves it pending and opens an
  "Amount differs" check; a second payment of a paid bill opens "Bill paid twice".
- If Safaricom cannot make the QR, the payment stays pending: the bill prints the
  paybill and account number, and a customer who types them still pays it.
- QR payments are never status-queried (`payments:reconcile` skips them); only the
  confirmation settles them.
