# DNS Records — `ride.365sakartvelo.com`

> Set these in the cPanel **Zone Editor** for `365sakartvelo.com` (or
> wherever DNS for the parent domain lives). cPanel will keep doing
> DNS for the parent — we just delegate the subdomain to a single
> VPS via an `A` record.

## Required records

Replace `<VPS-IPv4>` with your provider-supplied address (e.g.
`5.75.x.x` for Hetzner, `139.59.x.x` for DigitalOcean).
Replace `<VPS-IPv6>` with the IPv6 address if your VPS has one;
otherwise omit the `AAAA` line.

| Name                                | Type   | Value                | TTL  | Purpose                                  |
|-------------------------------------|--------|----------------------|------|------------------------------------------|
| `ride.365sakartvelo.com.`           | A      | `<VPS-IPv4>`         | 300  | Main app + API + admin + WS              |
| `ride.365sakartvelo.com.`           | AAAA   | `<VPS-IPv6>`         | 300  | Optional IPv6 fallback                   |
| `_acme-challenge.ride.365sakartvelo.com.` | TXT | (managed by certbot) | —    | Let's Encrypt validation (auto-set on first cert request) |

300-second TTL gives us fast cutover during the bring-up. After
you've verified everything works, raise it to 3 600.

## Optional: split host names

If you later want to separate the WebSocket broker, admin, and API
onto distinct hostnames (better for cookie scoping + CDN rules),
add:

| Name                               | Type | Value       | Notes                                          |
|------------------------------------|------|-------------|------------------------------------------------|
| `api.ride.365sakartvelo.com.`      | A    | `<VPS-IPv4>` | nginx routes by Host header to PHP-FPM        |
| `realtime.ride.365sakartvelo.com.` | A    | `<VPS-IPv4>` | nginx proxies to Reverb on `127.0.0.1:8080`  |
| `admin.ride.365sakartvelo.com.`    | A    | `<VPS-IPv4>` | Filament panel; same PHP-FPM upstream         |

For the **pilot**, all four hostnames resolving to the same VPS is
fine and keeps SSL management simpler. We pick this apart in
Phase 3 when load justifies separate boxes.

## Cloudflare / Imunify (optional)

cPanel sometimes proxies subdomains through Cloudflare or Imunify
by default. **Disable proxying** for `ride.365sakartvelo.com` —
nginx on the VPS handles TLS + DDoS at the level we need for pilot.
A grey-cloud (DNS only) record is what we want.

If you keep Cloudflare in front later:
- WebSocket upgrade must be allowed (it is by default on free).
- Set the SSL mode to "Full (strict)" so the VPS cert is honoured.
- Disable the Rocket Loader / minification — it breaks the
  Filament admin Livewire calls.

## SPF / DKIM (email — out of scope)

`365sakartvelo.com` likely already has SPF and DKIM set up for
email. Adding `ride.` as a subdomain does NOT affect email
deliverability — the pilot uses Twilio for SMS and (eventually)
SendGrid for transactional email, both of which sign as their own
domain.

## Verification

After the records propagate (usually < 5 min for 300 s TTL):

```bash
# From your laptop, NOT the server:
dig +short ride.365sakartvelo.com A
# → expect <VPS-IPv4>

dig +short ride.365sakartvelo.com AAAA
# → expect <VPS-IPv6> or empty if no AAAA set

# Verify cPanel isn't proxying:
dig +short ride.365sakartvelo.com NS
# → should be your cPanel host's nameservers, NOT cloudflare/imunify
```

If `dig` keeps returning the cPanel-provider's parking IP, double-
check the Zone Editor in cPanel. Sometimes the subdomain gets
created with a wildcard A record pointing at the cPanel shared IP
— delete that record first, then add the `A` record above.

## DNS propagation cache

Browsers + OS DNS resolvers cache 30 min or more by default. If you
test on a laptop right after a change, flush:

- macOS: `sudo dscacheutil -flushcache; sudo killall -HUP mDNSResponder`
- Linux: `sudo resolvectl flush-caches`
- Windows: `ipconfig /flushdns`
- Chrome: `chrome://net-internals/#dns` → "Clear host cache"

## When you'll need to update DNS

- Switching VPS providers — update the `A` record to the new IP.
- Adding a second app server (sticky session WS) — switch from `A`
  to a Cloudflare load-balanced setup or use round-robin `A` records.
- Cutting over from pilot VPS to AWS — change `A` to the ALB
  CNAME via Route53. Plan a 5-minute cut-over window with TTL
  dropped to 60 the day before.
