# Cloudflare in front of the platform

All platform traffic (API, panels, payment callbacks) goes through Cloudflare, proxied. The app
takes the client IP from `CF-Connecting-IP` only when the connection comes from a Cloudflare edge
range or from one of our own proxies (`app/Http/Middleware/TrustCloudflare.php`). A direct
connection's forwarding headers are ignored, and `X-Forwarded-Host` / `-Port` are never trusted,
because the host picks the tenant.

Why it matters: without it every request carries a Cloudflare IP, so the Safaricom callback
allowlist refuses real callbacks and per-IP rate limits treat all clients behind one edge as one.

## App configuration

| Setting | Purpose |
| --- | --- |
| `vendapos.network.cloudflare_ips` | Fallback edge ranges, used until the first refresh succeeds |
| `network:refresh-cloudflare-ips` | Daily 04:00 Nairobi; fetches `api.cloudflare.com/client/v4/ips`, keeps the old list on any bad answer |
| `VENDAPOS_TRUSTED_PROXIES` | Our own proxies in front of the app (cloudflared, a load balancer), comma-separated IPs/CIDRs |

## Cloudflare dashboard (free plan)

1. DNS: every platform hostname **proxied** (orange cloud), including the callback host. Never
   publish the origin IP on a DNS-only record.
2. SSL/TLS: **Full (strict)** with a Cloudflare Origin Certificate on the server.
3. Security → Bots: **Bot Fight Mode off**. On the free plan it cannot be bypassed per path and
   can challenge Safaricom's server-to-server callbacks.
4. Security → WAF → custom rule: when URI path starts with `/webhooks/`, action **Skip** (managed
   rules and other custom rules). Callbacks are still checked by the app: secret URL token,
   Safaricom IP allowlist, matching `CheckoutRequestID`, plus status-query reconciliation.

## Server (Contabo)

- Firewall: accept 80/443 only from Cloudflare's ranges (or run cloudflared and open no web ports).
- Optional, stronger: Authenticated Origin Pulls with our own certificate, so only our zone can
  reach the origin.

## Before go-live

- One Daraja sandbox STK payment through Cloudflare with `VENDAPOS_PAYMENTS_ENFORCE_CALLBACK_IPS=true`;
  the callback must settle the payment (security log must not show `payments.callback_ip_not_allowed`).
- Re-check Safaricom's callback IP list in `config/vendapos.php` against the Daraja portal.
