# LUMN Tracking / SEO Tools

Developer documentation for the LUMN Tracking / SEO Tools system: what it
is, why it exists, and how to build on it safely.

- **Step 1** built the architectural foundation: settings, the
  feature-flag API, the event specification, the data-layer API, and the
  hooks a debugger uses. No tracking event actually fired yet.
- **Step 2** (this revision) implements the first usable layer of events on
  top of that foundation: phone clicks, email clicks, appointment clicks,
  directions clicks, and the explicit `data-lumn-event` markup mechanism.
  Form-plugin tracking (Gravity Forms, Formidable, etc.) is a later step
  and is intentionally not covered here.

## What this is, and why it exists

LUMN Utilities is already installed on production WordPress sites, many of
which already have their own Google Analytics 4 / Google Tag Manager
configuration. LUMN Tracking is **not** a replacement for that - it is a
standardization layer that, once an administrator opts in, generates
consistently-named, consistently-shaped events and hands them to the site's
existing data layer:

```
WordPress
    |
LUMN Utilities
    |
standardized LUMN event
    |
window.dataLayer
    |
existing GTM container
    |
GTM trigger
    |
GTM tag
    |
GA4 / Google Ads / other destination
```

LUMN Utilities owns event standardization. GTM remains entirely responsible
for deciding what happens with an event. This plugin never:

- creates a GTM container, tag, or trigger
- creates a GA4 property
- sends a GA4 (or any Google) request directly
- injects a GTM or GA4 snippet
- alters an existing GTM or GA4 configuration already on the site
- alters existing Rank Math (or other SEO plugin) behavior

## The opt-in requirement (read this before adding anything)

**Installing or updating LUMN Utilities must never change a site's existing
tracking behavior.** This is enforced architecturally, not just by
convention:

- The master switch (`LUMN Utilities -> SEO & Tracking -> Enable LUMN SEO &
  Tracking`) defaults to **off**, and only an administrator saving the
  settings form can turn it on.
- Every individual feature has its own toggle, also off by default.
- [`lumn_ut_tracking_feature_enabled()`](#feature-flag-api) fails closed on
  every axis: master off, feature off, or an unrecognized feature name all
  resolve to `false`. A missing option is never treated as enabled.
- The front-end script (`public/js/lumn-tracking.js`) is only enqueued -
  and `window.dataLayer` is only ever touched - when the master switch is
  on. A default install loads no LUMN tracking script and sends nothing to
  the data layer.
- [`lumn_ut_tracking_push_event()`](#php-api) and
  `LumnTracking.pushEvent()` both re-check the master switch and the
  relevant feature toggle themselves - they do not trust a caller to have
  checked first.

**Any new tracking feature must go through this system.** Do not add a
`wp_localize_script`/`dataLayer.push`/GTM/GA4 call anywhere else in the
plugin - route it through the registry and API described below so it
inherits the opt-in guarantees automatically.

## Where things live

| Piece | File |
| --- | --- |
| Feature registry, event registry, PII denylist | `register/tracking-registry.php` |
| Settings storage, feature-flag API, PHP push API, script enqueue, Settings API registration, known-appointment-URL lookup | `register/tracking.php` |
| Admin page rendering ("SEO & Tracking") | `admin/tracking-page.php` |
| Front-end data-layer abstraction (`LumnTracking.pushEvent()`, debugger) | `public/js/lumn-tracking.js` |
| Automatic + explicit click detection (phone/email/appointment/directions, `data-lumn-event`) | `public/js/lumn-tracking-events.js` |

## Settings

Everything lives in a single option, `lumn_ut_tracking_settings` (an
associative array), following the same "one option holding an open-ended
associative array" pattern used by `lumn_ut_locations`. Never read this
option directly - always go through `lumn_ut_get_tracking_settings()` (or,
better, the feature-flag API below), which merges whatever is stored onto
an all-`false` baseline so a key that has never been saved - or one a
future version of the plugin no longer recognizes - can never resolve to
enabled.

```php
$settings = Lumn\Utilities\lumn_ut_get_tracking_settings();
// array( 'master' => false, 'event_tracking' => false, 'form_tracking' => false, ... )
```

Recognized keys: `master`, plus every key in
`lumn_ut_tracking_feature_registry()`, in the order shown on the settings
screen: `phone_click_tracking`, `email_click_tracking`,
`appointment_click_tracking`, `directions_click_tracking`,
`event_tracking` (labeled **Explicit Event Tracking** - see below),
`form_tracking`, `download_tracking`, `video_tracking`,
`external_link_tracking`, `debugger`.

`event_tracking`/"Explicit Event Tracking" is a second, additional gate
specifically for the `data-lumn-event` markup mechanism (see "Explicit
detection" below) - it does not replace the resolved event's own feature
toggle. An explicitly-tagged phone link still requires **both** Explicit
Event Tracking **and** Phone Click Tracking to be on.

The settings screen (`LUMN Utilities -> SEO & Tracking`) shows every one of
these as its own checkbox, even the ones with no tracking code behind them
yet - each is labeled "Coming soon" in that case. Turning one on now is
harmless (it still does nothing until that feature ships) and means it
starts working with no further admin action once it does.

## Feature-flag API

This is the **one authoritative place** the rest of the plugin must use to
decide whether tracking may run. Do not read `lumn_ut_tracking_settings`
directly from anywhere else.

```php
Lumn\Utilities\lumn_ut_tracking_is_enabled(); // bool - the master switch only
Lumn\Utilities\lumn_ut_tracking_feature_enabled( 'form_tracking' ); // bool - master AND that feature
```

`lumn_ut_tracking_feature_enabled( $feature )` fails closed:

1. Master switch off -> `false`.
2. `$feature` not present in `lumn_ut_tracking_feature_registry()` -> `false`
   (a misspelled or removed feature name is never treated as enabled).
3. That feature's own toggle off -> `false`.
4. Otherwise -> `true`.

Client-side, `window.LumnTracking.isFeatureEnabled( feature )` mirrors the
same logic against the config the server localized for this request (see
below) - it never makes its own decision about what's enabled.

## The LUMN event specification

**Naming convention:** `lumn_[object]_[action]`, e.g. `lumn_form_submit`,
`lumn_phone_click`, `lumn_appointment_click`, `lumn_directions_click`,
`lumn_email_click`, `lumn_file_download`, `lumn_video_start`,
`lumn_video_complete`, `lumn_external_link`.

Every event is registered once, in `lumn_ut_tracking_event_registry()`
(`register/tracking-registry.php`), keyed by an upper-snake-case constant
name (`LUMN_FORM_SUBMIT`, `LUMN_PHONE_CLICK`, ...). That registry is the
single source of truth for:

- the dataLayer event `name`
- which feature toggle gates it (`feature`)
- its `lumn_event_category` / `lumn_event_action`
- the parameter keys it may carry (`params`) - see below
- a human-readable `description`, for the settings UI and any future
  debugger

**Do not hardcode an event name as a string anywhere else in the plugin.**
Add it to the registry once and reference it by its constant key.

### Standard parameters

Every event automatically carries:

```
event                   // the lumn_* event name
lumn_event_category     // e.g. "lead", "engagement"
lumn_event_action       // e.g. "phone_click"
```

Every event may additionally carry the base params
(`lumn_ut_tracking_base_event_params()`):

```
lumn_location    // where on the page, e.g. "header", "hero", "footer"
lumn_component   // what UI element, e.g. "primary_cta"
lumn_page_type   // what kind of page, e.g. "location_page", "contact_page"
```

...plus whatever event-specific params that event's registry entry
declares, e.g. `LUMN_FORM_SUBMIT` additionally allows `lumn_form_type`,
`lumn_form_id`, `lumn_form_name`.

Example (`lumn_phone_click`):

```javascript
{
    event: "lumn_phone_click",
    lumn_event_category: "lead",
    lumn_event_action: "phone_click",
    lumn_location: "header",
    lumn_component: "primary_cta"
}
```

Example (`lumn_form_submit`):

```javascript
{
    event: "lumn_form_submit",
    lumn_event_category: "lead",
    lumn_event_action: "form_submit",
    lumn_location: "contact_page",
    lumn_component: "form",
    lumn_form_type: "appointment"
}
```

**Any parameter key not declared for that event is silently dropped**, both
server-side and client-side - it never reaches the data layer. This is the
primary defense described in the PII/PHI section below.

## PII / PHI restrictions

This plugin is used primarily on dental/medical practice websites. **LUMN
tracking events must never carry patient-submitted form values or other
sensitive information** - no name, email, phone number, address, date of
birth, insurance information, message/comment body, or medical/treatment
detail. A future form-tracking implementation may send metadata about a
submission (form ID, form type, form name, page, location) but never the
contents of the fields that were submitted.

This is enforced in code, at three independent layers, not left to
developer discipline:

1. **Allowlist by design.** `lumn_ut_tracking_push_event()` (PHP) and
   `LumnTracking.pushEvent()` (JS) only accept parameter keys that the
   event's registry entry explicitly declares in `params`. There is no way
   to pass arbitrary/unregistered data through - a raw `$_POST` array (or
   any other blob) is never a valid `$params` argument; unrecognized keys
   are dropped, not forwarded.
2. **A hard denylist, checked even on declared keys.**
   `lumn_ut_tracking_forbidden_param_keys()` lists parameter key names
   (`name`, `email`, `phone`, `address`, `dob`, `insurance`, `message`,
   `medical`, `password`, etc.) that are stripped regardless of whether an
   event's registry entry lists them - so a future mistake in the registry
   itself doesn't become a leak.
3. **Scalars only, sanitized.** Any value that is not a string, number, or
   boolean (an array or object - e.g. a dumped set of form fields) is
   rejected outright. Every accepted string value is run through
   `sanitize_text_field()` server-side before it reaches the inline script.

When adding a new event to the registry, only add parameter keys that
describe **metadata about the interaction**, never the content a visitor
typed. If you are unsure whether a field is safe to include, leave it out.

This also means: never add the phone number itself to
`LUMN_PHONE_CLICK`'s params, never the email address to
`LUMN_EMAIL_CLICK`'s, and never the full destination URL to
`LUMN_APPOINTMENT_CLICK` or `LUMN_DIRECTIONS_CLICK` - a destination URL is
an easy way to accidentally smuggle a query string containing a patient's
identifying information into analytics. None of `LUMN_PHONE_CLICK`,
`LUMN_EMAIL_CLICK`, `LUMN_APPOINTMENT_CLICK`, or `LUMN_DIRECTIONS_CLICK`
declare any event-specific params in the registry for exactly this reason
- only the base params (`lumn_location`, `lumn_component`, `lumn_page_type`)
are available to them, and `lumn-tracking-events.js` never reads an
element's `href` into the payload.

## The data-layer API

### PHP (`register/tracking.php`)

```php
Lumn\Utilities\lumn_ut_tracking_push_event(
    'LUMN_PHONE_CLICK',
    array( 'lumn_location' => 'header' )
);
```

Use this from server-rendered code (a shortcode/template that wants to
fire an event as part of its own output). It:

1. Returns `false` immediately unless `lumn_ut_tracking_is_enabled()`.
2. Returns `false` for an unrecognized `$event_key` (logged via
   `error_log()` when `WP_DEBUG` is on).
3. Returns `false` unless that event's feature is enabled
   (`lumn_ut_tracking_feature_enabled()`).
4. Filters `$params` through the allowlist/denylist/scalar rules above.
5. Queues `window.dataLayer.push({...})` via `wp_add_inline_script()` on
   the tracking script handle - which is only registered on this request
   when tracking is enabled, so there is nothing to attach to (and this
   function safely no-ops) on a disabled site.

### JavaScript (`public/js/lumn-tracking.js`)

```javascript
window.LumnTracking.pushEvent('LUMN_PHONE_CLICK', {
    lumn_location: 'header'
});
```

Use this from any future client-side interaction (a click handler, a form
submit listener, etc.) - this will be the primary path for most real
events, since most of the events in the spec (phone/email/appointment/
directions clicks, external links, video engagement) are inherently
client-side. It applies the exact same fail-closed and allowlist/denylist
rules as the PHP function, evaluated against the config the server
localized for this request (`window.lumnTrackingConfig` - only present at
all when tracking is enabled). `window.dataLayer` is created lazily, only
inside `pushEvent()`, only when an event actually gets pushed - never on
script load.

## Click tracking (Step 2)

`public/js/lumn-tracking-events.js` attaches exactly one delegated click
listener to `document` (capture phase), the first time it runs, and
classifies every click either **explicitly** (via a `data-lumn-event`
attribute) or **automatically** (from the clicked link's `href`). Both
paths ultimately call `LumnTracking.pushEvent()`, so every safety rule
described above (feature gating, the param allowlist/denylist, scalar-only
values) applies identically no matter how the event was detected.

The script is only enqueued when tracking is enabled overall (same
condition as `lumn-tracking.js`), and self-bails - attaching no listener
at all - if every click-related feature (`phone_click_tracking`,
`email_click_tracking`, `appointment_click_tracking`,
`directions_click_tracking`, `event_tracking`) is off.

### Automatic detection

Only plain `<a href="...">` elements are considered, and only when no
ancestor carries `data-lumn-event` (see precedence below). Checked in this
fixed order - the first match wins:

| href pattern | Event | Feature toggle |
| --- | --- | --- |
| `tel:...` | `lumn_phone_click` | Phone Click Tracking |
| `mailto:...` | `lumn_email_click` | Email Click Tracking |
| Matches this site's configured Appointments link (site-wide `[lumn_social_url name="appointments"]`, or any Practice Location's per-location override) | `lumn_appointment_click` | Appointment Click Tracking |
| `maps.google.com`, `google.com/maps` or `www.google.com/maps`, `goo.gl/maps`, `maps.apple.com`, `bing.com/maps` or `www.bing.com/maps`, `waze.com` | `lumn_directions_click` | Directions Click Tracking |

A link matching none of these produces no event. Appointment detection is
deliberately **not** based on button text or a hardcoded third-party
booking domain - it only ever matches a URL an administrator has actually
configured on this site (via `lumn_ut_tracking_known_appointment_urls()`
in `register/tracking.php`), compared host+path, ignoring a trailing-slash
difference. If this site has no Practice Locations and no site-wide
Appointments link configured, automatic appointment detection simply never
matches anything - use explicit tracking (below) for appointment CTAs on
such a site.

### Explicit detection

Any element - not just links - can be explicitly marked:

```html
<a href="/request-appointment/"
   data-lumn-event="appointment_click"
   data-lumn-location="hero"
   data-lumn-component="primary_cta">
    Request Appointment
</a>
```

`data-lumn-event` is resolved against the server-localized event registry,
accepting any of (case-insensitive):

- a registry key - `LUMN_APPOINTMENT_CLICK`
- the dataLayer event name - `lumn_appointment_click`
- the short action form - `appointment_click`

An unrecognized value **fails safe**: no `dataLayer.push()` happens, and
(with Debug Mode on) it is logged as suppressed with the reason
"Unrecognized data-lumn-event value."

#### Supported `data-lumn-*` attributes

Only these three are ever read. Nothing else - including arbitrary
`data-*` attributes a theme or other plugin might add to the same element
- is copied into the event payload:

| Attribute | Maps to |
| --- | --- |
| `data-lumn-event` | which LUMN event to fire (required) |
| `data-lumn-location` | `lumn_location` param |
| `data-lumn-component` | `lumn_component` param |

Explicit tracking requires **two** toggles to be on: **Explicit Event
Tracking** (the markup mechanism itself) and the resolved event's own
feature toggle (e.g. Phone Click Tracking for a `data-lumn-event="phone_click"`
element). This lets an administrator turn off `data-lumn-event` scanning
site-wide (e.g. if it conflicts with another plugin's markup) without
touching automatic detection, while still keeping every event's own
per-type toggle authoritative.

### Detection precedence

```
Explicit data-lumn-event (nearest ancestor)
        |
        v
   Automatic classification (tel: / mailto: / appointment URL / maps URL)
```

If the clicked element or any ancestor carries `data-lumn-event`, that
resolution is used and automatic classification is **never** attempted for
that click - so a link that is simultaneously a `tel:` link and explicitly
tagged `data-lumn-event="appointment_click"` fires exactly one event (the
explicit one), never two.

### Duplicate-event prevention

- Exactly one `click` listener is attached to `document`, once, guarded by
  a `window.__lumnTrackingEventsBound` flag so an accidental double-enqueue
  of the script (or a future re-run of its init code) cannot attach a
  second listener and double-fire events.
- Because detection is delegated (matched against `event.target` at click
  time via `Element.closest()`), it requires no per-element setup, so
  **dynamically inserted content** (a link a JS widget inserts after page
  load, a lightbox, an AJAX-loaded block) is covered automatically with no
  `MutationObserver` and no re-initialization step.
- Explicit-vs-automatic precedence (above) is what prevents one physical
  link from producing two events when it would otherwise qualify for both.

### Debugging

The `debugger` feature toggle (gated by the master switch, like every
other feature) makes `LumnTracking.pushEvent()` and
`LumnTracking.reportSuppressed()` clearly distinguish, in the browser
console:

- **`[LUMN Tracking] Event detected: lumn_phone_click`** - a collapsed
  console group showing `Category`, `Action`, and every included param
  (e.g. `lumn_location`, `lumn_component`), for an event that actually
  reached `dataLayer.push()`.
- **`[LUMN Tracking] Event suppressed: lumn_email_click`** - the same
  format, with a `Reason` line (e.g. `Feature "email_click_tracking" is
  disabled.`, `Unrecognized LUMN event key.`, `Explicit Event Tracking is
  disabled.`, `Unrecognized data-lumn-event value - no event was sent.`),
  for a click that was detected but did not fire.

Every fired event also dispatches a `lumn:tracking:event` `CustomEvent` on
`window` with `detail: { key, event, detail }`; every suppressed one
dispatches `lumn:tracking:suppressed` the same way. This is the hook a
future visual debugger UI can listen to
(`window.addEventListener('lumn:tracking:event', ...)`) without
duplicating any event-generation logic - building that UI is still out of
scope for this step.

**None of this produces any console output or dispatches any browser
event when the Debugger toggle is off** - there is no production logging
cost to leaving it disabled (the default).

### Recommended GTM triggers (documentation only)

LUMN Utilities never creates these - set them up in GTM yourself if you
want an existing container to react to a LUMN event. Every event below
uses a GTM **Custom Event** trigger matching the `event` name:

| LUMN Event | Recommended GTM Trigger | Custom Event Name |
| --- | --- | --- |
| `lumn_phone_click` | Custom Event | `lumn_phone_click` |
| `lumn_email_click` | Custom Event | `lumn_email_click` |
| `lumn_appointment_click` | Custom Event | `lumn_appointment_click` |
| `lumn_directions_click` | Custom Event | `lumn_directions_click` |

`lumn_event_category`, `lumn_event_action`, `lumn_location`, and
`lumn_component` are all available as Data Layer Variables inside GTM once
you create them there (Variable type: **Data Layer Variable**, matching
variable name).

## Adding a new event (for future steps)

1. Add an entry to `lumn_ut_tracking_event_registry()` in
   `register/tracking-registry.php`: name (`lumn_[object]_[action]`),
   `feature` (add a new feature key to `lumn_ut_tracking_feature_registry()`
   first if needed), `category`, `action`, `description`, and `params`
   (metadata keys only - see PII/PHI rules above).
2. Call `lumn_ut_tracking_push_event()` or `LumnTracking.pushEvent()` from
   the feature's actual implementation code, passing only params declared
   in step 1.
3. If the feature is new, add its toggle's admin UI description and flip
   `implemented` to `true` for it in `lumn_ut_tracking_feature_registry()`
   (this only changes the "Coming soon" badge - the toggle itself already
   exists and already defaults to off).
4. Do not flip any toggle's default to "on" - every feature must remain
   opt-in.

## Manual testing (staging/test site)

1. `LUMN Utilities -> SEO & Tracking` -> check **Enable LUMN SEO &
   Tracking** (master) -> Save Changes.
2. Check one event's toggle, e.g. **Phone Click Tracking** -> Save
   Changes.
3. Open the site's front end (a page with a `tel:` link) in a new tab.
4. Open the browser console and run `window.dataLayer` once before
   clicking anything - confirm it is `undefined` (nothing has pushed to it
   yet, even though tracking is enabled - it's only created lazily on the
   first actual push).
5. Click the phone link.
6. Run `window.dataLayer` again - the last entry should look like:
   ```json
   { "event": "lumn_phone_click", "lumn_event_category": "lead", "lumn_event_action": "phone_click" }
   ```
7. Click a `mailto:` link on the same page (Email Click Tracking is still
   off) - confirm `window.dataLayer.length` did not change.
8. To see the debugger: also check **Debugger** under SEO & Tracking ->
   Save Changes -> reload the page -> click a tracked link again. The
   console should show a collapsed `[LUMN Tracking] Event detected: ...`
   group; clicking something that's detected but disabled (e.g. that same
   mailto: link) should show `[LUMN Tracking] Event suppressed: ...` with
   a `Reason`.
9. To test explicit tracking: add
   `data-lumn-event="appointment_click" data-lumn-location="test"` to any
   element's markup, enable both **Explicit Event Tracking** and
   **Appointment Click Tracking**, reload, and click it - confirm
   `lumn_appointment_click` with `lumn_location: "test"` appears.
10. Uncheck the master switch and confirm no further clicks push anything
    (and, after a fresh page load, that no `lumn-ut-tracking*` script tag
    is present in the page source at all).
