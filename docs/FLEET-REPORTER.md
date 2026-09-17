# Fleet Reporter

The reporter builds a snapshot of this site and posts it, signed, to a central
collector. It is one half of the LUMN Fleet pipeline; the other half (the
collector, the evaluator, the Google Sheet) lives in the `lumn-fleet` repo.

## What it is not

**It never receives anything.** There is no REST route, no rewrite rule, no
query var, no `admin_post_nopriv` handler — nothing this file adds is reachable
from outside the site. The collector's response body is read by nothing: only
the HTTP status code is recorded, and only so the admin card can say whether the
last send worked.

Because of that, nothing the collector could say can change this site's
behaviour. A collector that is down, misconfigured, or outright hostile gains no
foothold here.

**It never displays an assessment.** The pipeline computes a site's tier
centrally and writes it to the Google Sheet. It is never sent back and is never
shown in this plugin. A tech who cannot see a tier cannot act on a stale one,
and thresholds can move without anything on a client site knowing.

## It ships disabled

Every setting is a `wp-config.php` constant and there is **no admin toggle**. A
fresh install — or a new plugin version landing somewhere unexpected — sends
nothing at all until somebody edits `wp-config.php` on that specific site.

The signing key lives in `wp-config.php` rather than the database on purpose. A
database dump is a routine thing to hand around (migrations, backups, support
tickets) and a key in one is a key that has leaked. It also means restoring a
database cannot silently re-enable reporting on a site that was switched off.

## Switching it on

Add to `wp-config.php`, above the `/* That's all, stop editing! */` line:

```php
define( 'LUMN_FLEET_REPORTER_ENABLED', true );
define( 'LUMN_FLEET_REPORTER_URL',     'https://lumn-fleet-receiver-test.example.workers.dev/' );
define( 'LUMN_FLEET_REPORTER_KEY',     '...64 random characters, unique per site...' );
```

On Kinsta that is the whole block — the site works out its own id. `LUMN_FLEET_SITE_ID` is only
needed somewhere else, and the Developers page leaves it out of the paste block when it is not.

| Constant | Required | Notes |
| :- | :- | :- |
| `LUMN_FLEET_REPORTER_ENABLED` | yes | Must be boolean `true`. A quoted `'true'` or a `1` is **not** — the check is `=== true`, so anything else leaves the reporter silently off. |
| `LUMN_FLEET_REPORTER_URL` | yes | **Must be `https`**, and must point at the receiver's **root path** — see below. A plaintext URL is refused, not warned about: the payload carries owner names, email addresses and the tech's own notes. |
| `LUMN_FLEET_REPORTER_KEY` | yes | Unique per site, and must match the value stored for this site id in the receiver's `SITE_KEYS` namespace. Both sides or neither. |
| `LUMN_FLEET_SITE_ID` | **only off Kinsta** | Ignored on Kinsta, where the id is derived — see below. Elsewhere use `<site-name>-<environment>`. With neither a derivation nor this constant the reporter refuses to send rather than inventing an identity. |

All of these are checked before a single byte leaves the site, and the Developers
page shows which one is missing. It also renders a ready-to-paste block with a
freshly generated key, which is easier than assembling this by hand.

### Site ids are derived, not typed

On Kinsta the site reads its own id off its filesystem path. Kinsta hosts every environment at
`/www/{site-name}_{environment-id}/public/`, so the id is `{site-name}-{environment-id}` —
`lumntestq-144`, `coulonwatts-417`. Nothing to configure and nothing to mistype.

**This was a hand-typed constant and it drifted immediately.** On the first site ever configured
for the fleet, `LUMN_FLEET_SITE_ID` read `lumntest-stg` while Kinsta called the site `lumntestq`.
The site id is the signing identity and the primary key of every snapshot, so a typo is not
cosmetic — it files a site under a name nothing else in the system uses.

**Every environment needs its own id.** Two sites sharing one would have their snapshots
interleaved in the collector — and nothing would report an error, because the receiver
authenticates per site id and would accept both against whichever key is registered. The Sheet
would then show a tier computed from a mixture of two sites. The derived form cannot collide: the
trailing number is per-environment, so a site's live and staging installs differ by construction.

That also retires the `WP_ENVIRONMENT_TYPE` guessing this section used to describe. It is still
worth setting — WordPress and other plugins use it — but the reporter no longer needs it on
Kinsta.

**If the constant is set and Kinsta derives something different, the derived name wins** and the
Developers page says so. Register the derived id with the collector *before* the next send, or it
comes back `401 unknown_site`; then delete the constant.

#### Off Kinsta

Set `LUMN_FLEET_SITE_ID` by hand as `<site-name>-<environment>`, with the environment one of
`prod`, `stg`, `dev`, `local`. The Developers page notes an id that does not end in one of those.
It is only a note; sending continues either way.

There is deliberately **no hostname fallback**. It used to be one, and it was a bad identity: it
changes when a domain changes, silently re-filing a site's entire history under a new name. With
nothing derivable and nothing configured the readiness gate fails and the page says which constant
is missing.

### The URL must be the receiver's root

The receiver serves exactly one route, `POST /`. **Any path segment returns 405**
and the site logs "Collector responded 405" — so `https://…workers.dev/ingest` is
wrong and `https://…workers.dev/` is right.

The same applies to a custom domain: map it at the root. A Cloudflare route with a
path prefix (`getlumn.com/fleet/*`) will 405, because the pathname the Worker sees
is `/fleet/...`, not `/`.

## Scheduling

Daily. Action Scheduler is used when the site has it (it ships with WooCommerce
and others, and survives a slow cron far better), otherwise WP-Cron. The first
run is offset by a random interval of up to an hour so a fleet-wide deploy does
not have every site posting in the same second.

A site that is not fully configured holds no schedule at all, so switching the
reporter off genuinely stops it rather than leaving a live cron event that
no-ops on every run.

Super admins get a **Send now** button on the Developers page. It is an
`admin-post` form — capability-checked and nonce-checked — not a REST route.

## The signature

```
signing base = "v1" . "." . timestamp . "." . site_id . "." . raw_request_body
signature    = HMAC-SHA256( signing_base, per_site_key )
```

Sent as:

| Header | Value |
| :- | :- |
| `X-Lumn-Site` | the site id |
| `X-Lumn-Timestamp` | Unix seconds |
| `X-Lumn-Signature` | hex HMAC-SHA256 |
| `X-Lumn-Signature-Version` | `v1` |

The timestamp and site id are **inside** the signed material, not just headers
alongside it. If they were not, either could be swapped freely while the
signature still verified, which would defeat both the receiver's replay window
and its per-site key lookup.

A receiver must:

1. Reject anything whose `X-Lumn-Signature-Version` it does not know.
2. Reject a timestamp outside its window (the sender assumes ±300s).
3. Look the key up **by the site id in the header**, then verify — and compare
   with a constant-time comparison (`hash_equals` or equivalent), never `===`.
4. Reject a signature it has already seen, so a captured request cannot be
   replayed inside the window.
5. Store the raw body unparsed, return `202` with an empty body, and expose no
   read route of any kind.

`lumn_ut_fleet_verify()` in `register/fleet-reporter.php` is the exact
counterpart of the signing and is the reference for step 3. It is unused by this
plugin — there is no inbound path here — and exists to be read.

## What is in the payload

Site URL and id, WordPress and PHP versions, the active theme and its parent
with versions, the **full** plugin inventory with versions and active state
(inactive plugins included — an abandoned page builder sitting deactivated still
says something about how the site was built), the earliest media library upload
date, Kinsta's own identity for the install, every Site Profile field, and the
three tech input fields.

**The earliest upload date is collected and deliberately not used.** It is the
obvious-looking build-date fallback and it is a trap: LUMN builds routinely start
from cloned environments, so the media library arrives carrying the *source*
site's dates. That error is not occasional and not random — it always makes a
site look older than it is, which manufactures Priority sites that are not
priorities. `launch_date` from the Site Profile is the only build date, and a
site with none simply does not fire `build_age`.

**Kinsta identity** is `{site_name, environment_id}`, parsed from `ABSPATH`:
Kinsta hosts every environment at `/www/{site_name}_{environment_id}/public/`.
It is collected because `LUMN_FLEET_SITE_ID` is typed by hand and drifts — on the
first site configured for the fleet the constant said `lumntest-stg` while Kinsta
called the site `lumntestq`. Both values are empty on a non-Kinsta host, and
empty means "no answer", never "not on Kinsta".

It carries no computed tier, track, or trigger, and never will: those exist only
in the Sheet.

## Logging

The last 10 send outcomes are kept in the `lumn_ut_fleet_reporter_log` option
and shown on the Developers page.

**PII is in scope.** The payload carries owner names, email addresses and
free-text notes. The log records only a timestamp, a trigger, a boolean, and a
status or transport-error string — never payload content and never the key.
Keep it that way.
