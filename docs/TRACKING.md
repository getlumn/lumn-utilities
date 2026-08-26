# LUMN Tracking / SEO Tools

Developer documentation for the LUMN Tracking / SEO Tools system: what it
is, why it exists, and how to build on it safely.

- **Step 1** built the architectural foundation: settings, the
  feature-flag API, the event specification, the data-layer API, and the
  hooks a debugger uses. No tracking event actually fired yet.
- **Step 2** implements the first usable layer of click events: phone,
  email, appointment, directions clicks, and the explicit `data-lumn-event`
  markup mechanism.
- **Step 3** adds provider-agnostic form submission tracking - Gravity
  Forms and Formidable Forms today, with an adapter pattern future
  providers plug into. A GTM administrator sees the same
  `lumn_form_submit` event regardless of which plugin generated it.
- **Step 4** (this revision) makes the system observable: a front-end
  Tracking Debugger overlay, an admin-facing Event Catalog, a Tracking
  Health Checker, and a deterministic GTM Setup Guide. Nothing here
  enables tracking, injects GTM/GA4, or talks to anything outside this
  site - it only helps answer "what does this site generate, and why
  isn't it working."

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
| Feature registry, event registry, form provider/type registries, PII denylist | `register/tracking-registry.php` |
| Settings storage, feature-flag API, PHP push API, cross-request relay, script enqueue, Settings API registration, known-appointment-URL lookup | `register/tracking.php` |
| Admin page rendering ("SEO & Tracking") | `admin/tracking-page.php` |
| Provider-agnostic Form Tracking API, per-form config, Gravity Forms + Formidable Forms adapters, "Form Tracking Providers"/"Tracked Forms" settings UI | `register/form-tracking.php` |
| Front-end data-layer abstraction (`LumnTracking.pushEvent()`, `consumeRelay()`, debugger) | `public/js/lumn-tracking.js` |
| Automatic + explicit click detection (phone/email/appointment/directions, `data-lumn-event`) | `public/js/lumn-tracking-events.js` |
| Form-submission relay consumption (Gravity Forms / Formidable "confirmation shown" listeners) | `public/js/lumn-tracking-forms.js` |
| Debug overlay activation/authorization, its script enqueue, Health Checker, GTM recipe generator | `register/tracking-debugger.php` |
| Admin page rendering (Debugger / Event Catalog / Health Check / GTM Guide tabs) | `admin/tracking-debugger-page.php` |
| Front-end debug overlay panel (Recent Events, Test Event tool, page scanner) | `public/js/lumn-tracking-debugger.js` |
| Automatic CTA classification config (URL patterns/domains/excluded domains), its Settings API registration, download-extensions registry | `register/engagement-tracking.php` |
| Download/external-link classification + `data-lumn-track="false"` suppression (extends the Step 2 click engine) | `public/js/lumn-tracking-events.js` |
| Native HTML5 `<video>` engagement tracking (start/progress/complete) | `public/js/lumn-tracking-video.js` |

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
`form_tracking`, `download_tracking`, `external_link_tracking`,
`video_tracking`, `cta_classification` (labeled **Automatic CTA
Classification** - see "Engagement, download, video, and CTA tracking
(Step 5)" below), `debugger` - plus one `form_tracking_{provider}`
key per entry in `lumn_ut_tracking_form_provider_registry()`
(`form_tracking_gravity_forms`, `form_tracking_formidable_forms`), added
automatically by `lumn_ut_tracking_default_settings()` so a new provider
only needs registering once. Per-form tracking config (which individual
forms are tracked, as what type, at which location) lives in a **separate**
option, `lumn_ut_form_tracking_config` - see "Form tracking" below.

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
`lumn_email_click`, `lumn_sms_click`, `lumn_file_download`,
`lumn_video_start`, `lumn_video_progress`, `lumn_video_complete`,
`lumn_external_link`.

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
    lumn_form_id: "3",
    lumn_form_name: "Request Appointment",
    lumn_form_type: "appointment",
    lumn_form_provider: "gravity_forms",
    lumn_location_id: "2",
    lumn_location_name: "Downtown Office"
}
```

(`lumn_location_id`/`lumn_location_name` only appear when the form is
associated with a Practice Location - see "Form tracking" below;
`lumn_location`/`lumn_component`/`lumn_page_type`, the generic base
params, are also available to `lumn_form_submit` but aren't populated by
the current provider adapters.)

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
`LUMN_PHONE_CLICK`'s or `LUMN_SMS_CLICK`'s params, never the email address
to `LUMN_EMAIL_CLICK`'s, and never the full destination URL to
`LUMN_APPOINTMENT_CLICK` or `LUMN_DIRECTIONS_CLICK` - a destination URL is
an easy way to accidentally smuggle a query string containing a patient's
identifying information into analytics. None of `LUMN_PHONE_CLICK`,
`LUMN_SMS_CLICK`, `LUMN_EMAIL_CLICK`, `LUMN_APPOINTMENT_CLICK`, or
`LUMN_DIRECTIONS_CLICK` declare any event-specific params in the registry
for exactly this reason
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
| `sms:...` | `lumn_sms_click` | Phone Click Tracking |
| Matches this site's configured Appointments link (site-wide `[lumn_social_url name="appointments"]`, or any Practice Location's per-location override) | `lumn_appointment_click` | Appointment Click Tracking |
| `maps.google.com`, `google.com/maps` or `www.google.com/maps`, `goo.gl/maps`, `maps.apple.com`, `bing.com/maps` or `www.bing.com/maps`, `waze.com` | `lumn_directions_click` | Directions Click Tracking |

`lumn_sms_click` (`LUMN_SMS_CLICK`) reuses the **Phone Click Tracking**
toggle rather than getting its own - an `sms:` link is the same
click-to-contact-a-phone-number action as a `tel:` link, just via text
instead of a call, so there's no separate feature to turn on. Before this
was added, an `sms:` link had no dedicated `tel:`-style guard in
`classifyAnchor()`, so it fell through to the generic external-link check
- and since `new URL('sms:...')` parses with an empty hostname (same as
`tel:`/`mailto:`/`javascript:`), it incorrectly registered as
`lumn_external_link` when External Link Tracking was on.

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
| `lumn_sms_click` | Custom Event | `lumn_sms_click` |
| `lumn_appointment_click` | Custom Event | `lumn_appointment_click` |
| `lumn_directions_click` | Custom Event | `lumn_directions_click` |
| `lumn_form_submit` | Custom Event | `lumn_form_submit` |

`lumn_event_category`, `lumn_event_action`, `lumn_location`, and
`lumn_component` are all available as Data Layer Variables inside GTM once
you create them there (Variable type: **Data Layer Variable**, matching
variable name) - as are `lumn_form_id`, `lumn_form_name`, `lumn_form_type`,
`lumn_form_provider`, `lumn_location_id`, and `lumn_location_name` for
`lumn_form_submit` specifically.

## Form tracking (Step 3)

A provider-agnostic layer that normalizes successful form submissions from
different WordPress form plugins into the single `lumn_form_submit` event
- a GTM administrator never needs to know or care which plugin generated a
given submission:

```
Gravity Forms submission  -\
                             > lumn_ut_form_tracking_submit()  ->  lumn_form_submit  ->  dataLayer  ->  GTM
Formidable submission     -/
```

Everything lives in `register/form-tracking.php`, split from
`register/tracking.php`/`register/tracking-registry.php` the way
`register/locations.php` is split from the rest of the plugin - forms
logic in one place, the generic tracking core untouched.

### The opt-in chain

Four levels, all independently required, checked in this order:

```
Master switch (is_enabled())
       |
Form Tracking (feature_enabled('form_tracking'))
       |
This provider's own toggle (tracking_form_provider_enabled($provider))
       |
This specific form's own toggle (per-form config, default OFF)
       |
       v
  lumn_form_submit
```

`lumn_ut_tracking_form_provider_enabled( $provider )`
(`register/tracking.php`) checks the first three levels and fails closed
on every axis, exactly like `lumn_ut_tracking_feature_enabled()`: master
off, Form Tracking off, an unrecognized provider name, or that provider's
own toggle off all resolve to `false`. The fourth level -
`lumn_ut_form_tracking_get_form_config( $provider, $form_id )['enabled']`
- defaults to `false` for any form with no saved config, so **every
individual form must be explicitly turned on**, even when its provider and
Form Tracking are both already enabled (a site can have Form 3 tracked and
Forms 7/9 not, all under the same Gravity Forms install).

### Provider adapters

Each adapter (Gravity Forms / Formidable, both in
`register/form-tracking.php`) only needs to:

1. Detect a **successful** submission via the provider's own server-side
   hook (never DOM scraping, never "the submit button was clicked").
2. Read that provider's own form id/name.
3. Call `lumn_ut_form_tracking_submit( $provider, $form_id, $form_name )`.

Everything else - the opt-in chain above, PII filtering, building the
`lumn_form_submit` payload, and getting it to the browser - is handled
once, generically, by `lumn_ut_form_tracking_submit()` and the tracking
core it calls into. No adapter ever talks to `lumn_ut_tracking_relay_event()`,
the settings option, or `window.dataLayer` directly.

#### Gravity Forms

Hook: `gform_after_submission( $entry, $form )`. This is Gravity Forms'
own "an entry was just successfully saved" action - it only ever fires
after a successful submission (never on validation failure, never merely
because the form displayed or a field changed), and fires identically
whether the visitor used a normal postback or GF's AJAX (iframe)
submission, since both go through the same server-side entry-saving code.
Registered only when Gravity Forms is detected
(`class_exists('GFForms') || class_exists('GFAPI')`) - absent, this
integration never registers a hook and stays completely dormant.

Form listing for the admin UI uses `GFAPI::get_forms()`, wrapped in a
`class_exists()`/`method_exists()` check and a `try/catch`, so a future
Gravity Forms API change degrades to "no forms found" rather than a fatal
error.

#### Formidable Forms

Hook: `frm_after_create_entry( $entry_id, $form_id )`, at priority `30`
(Formidable's own documented recommendation, so this runs after
Formidable's core entry-creation processing finishes). An entry is only
ever created on a successful submission, so - like Gravity Forms' hook -
this is naturally success-only. Registered only when Formidable is
detected (`class_exists('FrmForm') || class_exists('FrmAppHelper')`).

Form listing tries `FrmForm::get_published_forms()`, falling back to
`FrmForm::getAll()` if unavailable, both guarded the same defensive way as
the Gravity Forms listing above.

### Why a relay, not a direct push

`lumn_ut_tracking_push_event()` (the Step 1 direct-push API) queues an
inline `<script>` on the *current* response. That works fine when a
form's confirmation renders inline, in the same request the submission
hook fired in - but it silently loses the event whenever that isn't true:

- **A redirect confirmation** (Gravity Forms "Page"/"URL" confirmation
  type, or Formidable's `redirect` action): the hook fires, but the
  response becomes a bare redirect before anything queued for output ever
  gets printed.
- **A hidden AJAX iframe** (Gravity Forms' classic AJAX mechanism): the
  hook fires and the script *would* print - but inside the iframe's own
  throwaway document, whose `window.dataLayer` is a different object than
  the visible parent page's. GTM watching the parent page never sees it.

`lumn_ut_tracking_relay_event()` (`register/tracking.php`) solves this
generically: it queues the already-filtered, already-safe event into a
short-lived (5 minute), non-sensitive cookie
(`lumn_ut_pending_form_event`, capped at 5 queued events) instead of
printing anything immediately. The front end then picks it up:

- `LumnTracking.consumeRelay()` (`public/js/lumn-tracking.js`) reads and
  clears that cookie and pushes every queued event through the normal
  `pushEvent()` - same fail-closed feature check, same param filtering.
  It runs automatically once, at script load, **only in the top-level
  document** (`window.self === window.top`) - this guard is what stops a
  Gravity Forms AJAX iframe from consuming (and clearing) the cookie
  before the parent page ever gets a chance to see it. This alone covers
  a plain postback and any redirect confirmation.
- `public/js/lumn-tracking-forms.js` covers the AJAX case *promptly*
  rather than waiting for the visitor's next page load: it listens for
  Gravity Forms' `gform_confirmation_loaded` and Formidable's
  `frmFormComplete` - both provider-documented jQuery events fired on the
  **parent** document only when a successful confirmation is actually
  displayed (never on a validation-error re-render) - and calls
  `consumeRelay()` again at that moment. Calling it twice for the same
  cookie is always safe: the first read clears it, so the second finds
  nothing.

Nothing PII-bearing is ever eligible to enter the relay cookie - it goes
through the exact same allowlist/denylist/scalar filtering as a direct
push before it's ever written (see `lumn_ut_tracking_relay_event()`), and
`pushEvent()` re-validates it again on the way out. A visitor who tampers
with their own (non-`httponly`, by necessity) cookie can only ever produce
another fully schema-conforming `lumn_form_submit`-shaped event in their
*own* browser - the same characteristic already true of anyone calling
`window.LumnTracking.pushEvent()` directly from devtools.

**Known limitation:** if a redirect confirmation sends the visitor to a
different domain, or they close the tab before their next page load on
this site, the relay cookie simply expires unused after 5 minutes - there
is no way to deliver an event to a page this site doesn't control.

### Duplicate-event prevention

- One submission -> one `gform_after_submission`/`frm_after_create_entry`
  call -> one relay-queue entry -> one `consumeRelay()` delivery (the
  cookie is cleared on first read, so any later read - a redundant
  provider event trigger, a page reload - finds nothing left to fire).
- The top-level-only guard (above) prevents an AJAX iframe from consuming
  the event before the parent page can.
- Because form-provider hooks are inherently success-only server-side
  signals, a validation failure never creates an entry, never fires the
  hook, and therefore never queues anything - regardless of how many times
  a visitor resubmits after fixing errors.

### Form type classification

An admin-assigned label describing what a form is *for*
(`lumn_ut_tracking_form_type_registry()`): `appointment`, `contact`,
`consultation`, `newsletter`, `insurance`, `employment`, or `other`. This
is deliberately **not** inferred from submitted field content - it's
either explicitly chosen by an administrator in the Tracked Forms table,
or (only as the *initial* suggested dropdown value for a form with no
saved config yet) guessed from the form's *name* via
`lumn_ut_form_tracking_suggest_type()`. That guess is a courtesy default
only - a form literally titled "Tell Us How We Can Help" is not assumed to
be an `appointment` form just because it contains helpful-sounding words;
once an administrator saves any value (including leaving the suggestion
as-is), that becomes the real, explicit, stored classification.

### Location association

If a form's per-form config has a `location_id` set, `lumn_form_submit`
includes `lumn_location_id`/`lumn_location_name`, resolved via
`lumn_ut_get_location()` (the existing Practice Locations system - see
`register/locations.php`; this step does not create a second location
system). A stale/deleted location reference, or no location configured at
all, simply omits both params rather than inventing a value.

### Form Tracking settings UI

Two new sections on `LUMN Utilities -> SEO & Tracking`, added to the same
settings form/group as everything else (so they save together and share
its capability check + Settings API nonce):

- **Form Tracking Providers** - one row per registered provider, showing
  a **Detected**/**Not detected** badge (`lumn_ut_form_tracking_provider_detected()`)
  and, when detected, a checkbox to enable that provider. Detection only
  changes what this page *shows* - a detected provider is never enabled
  automatically, and an enabled-but-undetected provider's checkbox simply
  isn't rendered (its stored value stays whatever it already was, still
  gated by "is this provider actually detected right now" wherever it's
  read).
- **Tracked Forms** - one table per detected provider (ID / Name / Type /
  Location / Tracking), reading live from that provider's own API
  (`lumn_ut_form_tracking_get_gravity_forms_list()` /
  `..._get_formidable_forms_list()`). Saved into
  `lumn_ut_form_tracking_config`, sanitized by
  `lumn_ut_form_tracking_sanitize_config()`, which validates shape
  (provider must be recognized, `form_type` must be a known value or
  falls back to `other`) but deliberately does **not** require the form to
  currently exist in that provider's live list - so temporarily
  deactivating a provider plugin never silently deletes its saved
  configuration.

### GA4 mapping (documentation only - not implemented)

LUMN Utilities does not send anything to GA4 directly; this is guidance
for whoever configures the GTM tag that reacts to `lumn_form_submit`:

| LUMN | Potential GA4 event | Notes |
| --- | --- | --- |
| `lumn_form_submit` where `lumn_form_type` is `appointment`, `contact`, or `consultation` | `generate_lead` | These represent an actual prospective-patient inquiry. |
| `lumn_form_submit` where `lumn_form_type` is `employment` | *(none recommended)* | A job application is not a patient lead - don't count it as one. |
| `lumn_form_submit` where `lumn_form_type` is `newsletter` | `sign_up` (or similar) | Not a lead in the sales sense. |

Filter on `lumn_form_type` inside the GTM trigger (e.g. "`lumn_form_type`
equals `appointment`") to scope a `generate_lead` tag to just the form
types that represent a real lead, rather than mapping every
`lumn_form_submit` the same way. This mapping decision belongs to the
site's marketing/analytics owner, configured entirely in GTM - LUMN
Utilities only ever provides the standardized event.

### Adding a new form provider

```
New Provider
    |
Detect it safely (class_exists()/function_exists() - never a hard dependency)
    |
Hook its own "submission succeeded" event (never DOM scraping)
    |
Normalize: form_id, form_name  ->  lumn_ut_form_tracking_submit($provider, $form_id, $form_name)
    |
    v
lumn_form_submit  (handled generically - not the new adapter's concern)
```

Concretely, to add e.g. "Contact Form 7":

1. Add `'contact_form_7' => array('label' => __('Contact Form 7', ...))` to
   `lumn_ut_tracking_form_provider_registry()`
   (`register/tracking-registry.php`) - this alone gives you a
   `form_tracking_contact_form_7` settings key (via
   `lumn_ut_tracking_default_settings()`, automatically) and a row in the
   Form Tracking Providers UI (automatically, since that UI loops the
   registry).
2. In `register/form-tracking.php`: a `lumn_ut_form_tracking_contact_form_7_detected()`
   check, registered the same `add_action('plugins_loaded', ...)` way as
   the existing two adapters.
3. Hook that provider's own success-only action/filter (check its docs for
   the equivalent of `gform_after_submission`/`frm_after_create_entry` -
   never a generic "form submitted" hook that also fires on validation
   failure, and never a purely client-side "submit clicked" signal).
4. In that handler, call
   `lumn_ut_form_tracking_submit('contact_form_7', $form_id, $form_name)`
   - nothing else. You do not need to understand
   `lumn_ut_tracking_relay_event()`, the settings option shape, or the
   front-end relay/debugger to add a provider; `lumn_ut_form_tracking_submit()`
   is the entire surface a new adapter needs.
5. Add a `lumn_ut_form_tracking_get_contact_form_7_list()` (best-effort,
   defensively guarded like the two existing ones) so the Tracked Forms
   table can list that provider's forms, and wire it into
   `lumn_ut_form_tracking_forms_table_callback()`.
6. Leave every new toggle's default at `false`.

## Engagement, download, video, and CTA tracking (Step 5)

Four independently-toggled features, all built on top of the Step 2 click
engine (`public/js/lumn-tracking-events.js`) and a new native `<video>`
engine (`public/js/lumn-tracking-video.js`): **Download Tracking**,
**External Link Tracking**, **Video Tracking**, and **Automatic CTA
Classification**. Every one of them defaults to off, same as every prior
feature, and requires the master switch plus its own toggle.

**This step is deliberately conservative.** A false positive (an event
firing for something that wasn't really that kind of interaction) or a
duplicate event (two events for one click) is treated as strictly worse
than not tracking an ambiguous interaction at all - every classification
rule below is a fixed, deterministic check, never a heuristic score or a
"probably."

### Download tracking

`classifyAnchor()` (in the same delegated click handler as Step 2)
recognizes a link as a download when its `href`'s path ends in an
extension from `lumn_ut_tracking_download_extensions()`
(`register/tracking-registry.php`):

```
pdf, doc, docx, xls, xlsx, ppt, pptx, csv, txt, rtf, odt, ods, odp, zip, rar, 7z
```

Audio/video extensions are intentionally excluded from this list - a
`.mp4`/`.mp3` link is Video Tracking's concern, not Download Tracking's, so
the same file can never fire both a download and a video event. A site can
extend the list without any admin UI, via the standard WordPress filter:

```php
add_filter('lumn_ut_download_extensions', function ($extensions) {
    $extensions[] = 'dicom';
    return $extensions;
});
```

**Exclusions** (never classified as a download, matched before the
extension check): any path containing `/wp-admin/`, `/wp-json/`,
`/wp-cron.php`, `admin-ajax.php`, or `/feed/`, and (already ruled out
earlier in `classifyAnchor()`) any `tel:`, `mailto:`, or `javascript:`
link. There is no `HEAD` request or file-existence check - classification
is extension-only, purely from the `href` string, so it never makes an
extra network call per link on the page.

**Payload** (`lumn_file_download` / `LUMN_FILE_DOWNLOAD`):

```javascript
{
    event: "lumn_file_download",
    lumn_event_category: "engagement",
    lumn_event_action: "file_download",
    lumn_file_type: "pdf",
    lumn_file_name: "new-patient-forms.pdf"
}
```

`lumn_file_name` is only ever the decoded final path segment (the file's
own name) - never the full URL, never any query string or token the link
might carry (e.g. a signed download URL). `lumn_file_type` is always the
lowercased extension.

### External link tracking

Any link whose resolved hostname differs from the current page's hostname,
that didn't already match a more specific automatic category above it in
the precedence chain (below), and whose hostname isn't in the configured
exclusion list, fires `lumn_external_link`. `tel:`, `mailto:`, and
`javascript:` links are never considered (ruled out earlier in
`classifyAnchor()`), and a same-page anchor (`href="#section"`) is never
"external" since it has no hostname of its own.

**Payload** (`lumn_external_link` / `LUMN_EXTERNAL_LINK`):

```javascript
{
    event: "lumn_external_link",
    lumn_event_category: "engagement",
    lumn_event_action: "external_link",
    lumn_external_domain: "www.facebook.com"
}
```

Only the bare hostname is ever sent (`lumn_external_domain`) - never the
full destination URL, and never any query string it might carry (a
referral/UTM parameter, or worse, something identifying). This is a
narrower version of the same rule already applied to
`LUMN_APPOINTMENT_CLICK`/`LUMN_DIRECTIONS_CLICK` above.

**Exclusions**: `external_link_excluded_domains`, an admin-configured list
(see "Automatic Classification Settings" below) of domains that should
never fire `lumn_external_link` even though they're technically
off-site - e.g. a separate patient-portal subdomain the practice considers
part of "their" experience. Excluding a domain here only means *this
specific event* doesn't fire for it - it does not suppress any other
event; a link to an excluded domain that also happens to match a
configured appointment-scheduling domain (below) still fires
`lumn_appointment_click` normally.

### Detection precedence

```
Explicit data-lumn-event (nearest ancestor)
        |
        v
data-lumn-track="false" (nearest ancestor) --> no event at all
        |
        v
Automatic classification, in order:
  tel: / mailto:
        |
  Known appointment URL (exact match, Step 2) or, only if
  Automatic CTA Classification is on, a configured URL pattern/domain match
        |
  Known directions/maps URL
        |
  Download (extension match)
        |
  External link (different host, not excluded)
        |
        v
   No event
```

The first match wins, exactly as in Step 2 - a PDF hosted on an external
scheduling domain that is *also* explicitly tagged
`data-lumn-event="appointment_click"` still fires exactly one event (the
explicit one, never also `lumn_file_download` or `lumn_external_link`). A
link that is both a `.pdf` and hosted off-site fires only
`lumn_file_download`, never additionally `lumn_external_link` - download
classification is checked first.

#### `data-lumn-track="false"`

Suppresses **automatic** classification only, on the tagged element or any
of its descendants - it never blocks an explicit `data-lumn-event` on that
same (or a more specific descendant) element, since explicit detection is
checked first, before the suppression check. Use it on a link that would
otherwise be automatically (mis)classified but genuinely shouldn't
generate a LUMN event - e.g. a same-domain "external-looking" staff
directory link a practice doesn't consider a meaningful conversion signal:

```html
<div data-lumn-track="false">
    <a href="https://provider-directory.example.com/dr-smith">Dr. Smith's directory profile</a>
</div>
```

### Video tracking

**Provider support: native HTML5 `<video>` only, in this step.** Before
building this, the existing LUMN site patterns
(`patterns/text-and-video.html`, `patterns/video-full.html`,
`blocks/dcmo-ut-su-lightbox/render.php`) were inspected first, per the
spec's own instruction. They use the third-party "Shortcodes Ultimate"
`[su_lightbox type="iframe" src="....mp4"]` shortcode - a lightbox popup
containing a **cross-origin `<iframe>` pointed directly at a raw `.mp4`
file**, not a YouTube/Vimeo embed. A cross-origin iframe showing a raw
media file exposes no JS API (no `postMessage` protocol, no accessible
DOM) - it cannot be reliably tracked at all, by this plugin or any script.
A `[su_lightbox type="video"]` (not used in the sampled patterns, but
possible elsewhere on a LUMN site) renders an actual `<video>` element,
as does WordPress core's own Video block or any theme's native markup -
those **are** reliably trackable, which is what this step implements.

**YouTube and Vimeo are explicitly deferred**, per the spec's own
permission to defer "rather than creating brittle tracking" when a
provider can't be implemented reliably without a large third-party
library or DOM-inference guessing. Nothing about the current architecture
(the `providers` object in `public/js/lumn-tracking-video.js`) needs to
change to add them later - each provider is just another
`providers.<name>.bind()` implementation using that platform's own
supported embed API (the YouTube IFrame API / Vimeo Player SDK), never DOM
scraping or polling.

`public/js/lumn-tracking-video.js` attaches capture-phase `play`,
`timeupdate`, and `ended` listeners to `document` once (native media
events don't bubble, so capture-phase delegation is required to catch
them without instrumenting every `<video>` element individually). Per-
element session state is tracked in a `WeakMap`, not a `data-*` attribute,
so it never pollutes the DOM and is garbage-collected naturally when an
element is removed.

- **`play` -> `lumn_video_start`**: fires once per playback session. A
  `pause()` followed by `play()` (resume) does **not** fire a second
  start - session state already has `started: true`. Starting playback
  again *after* `ended` fired (a replay) **does** fire a new
  `lumn_video_start` - a full replay is treated as a new session, matching
  how a marketer would actually want to count it.
- **`ended` -> `lumn_video_complete`**: fires once per session; a second
  native `ended` event (some browsers can fire it more than once) is
  guarded against.
- **`timeupdate` -> `lumn_video_progress`**: at most once per session per
  milestone (25/50/75), in order, computed as
  `currentTime / duration * 100`. Guarded against `duration` being `0`,
  `NaN`, or `Infinity` (a live stream or a video whose metadata hasn't
  loaded) - no progress events are ever generated in that case, rather
  than spamming milestones.

**Payload** (`lumn_video_start` / `LUMN_VIDEO_START`, same shape for
`lumn_video_progress` and `lumn_video_complete` with `lumn_video_percent`
added):

```javascript
{
    event: "lumn_video_start",
    lumn_event_category: "engagement",
    lumn_event_action: "video_start",
    lumn_video_title: "New Patient Welcome",
    lumn_video_provider: "html5",
    lumn_video_id: "welcome-video"
}
```

`lumn_video_id` comes from `data-lumn-video-id`, falling back to the
element's own `id` attribute, falling back to a generated
`video-N` - never anything derived from the visitor. `lumn_video_title`
comes from `data-lumn-video-title`, falling back to the element's `title`
or `aria-label` attribute, sanitized (HTML-stripped, length-capped); if
none of those exist, the param is simply omitted, never sent as an empty
string. Nothing about viewer identity, form input, the video's source URL,
or any query string is ever included.

### Automatic CTA classification

The pre-existing **Appointment Click Tracking** feature (Step 2) already
fires `lumn_appointment_click` for an exact match against an
administrator-configured Appointments URL (site-wide or per Practice
Location) - that behavior is unchanged and needs no additional toggle.

**Automatic CTA Classification** is a *separate*, additional toggle that
extends appointment detection with two more, still fully
administrator-configured, deterministic signals - never page text, button
copy, or a hardcoded list of third-party scheduling vendors:

1. **A configured URL path pattern** (`appointment_url_patterns`) - a
   simple string prefix an internal path must start with, e.g.
   `/request-appointment/` matching `/request-appointment/new-patient/`.
2. **A configured scheduling domain** (`appointment_domains`) - matches
   that exact host or any subdomain of it (e.g. configuring
   `scheduler.example.com` also matches `booking.scheduler.example.com`).

Both lists are entirely optional and empty by default - configuring
nothing here means Automatic CTA Classification, even when its toggle is
on, never additionally matches anything beyond the exact-URL behavior
Appointment Click Tracking already had. There is no confidence score or
percentage exposed anywhere in this system - a URL either matches a
configured rule or it doesn't; deliberately not attempting to be "smarter"
than that is what keeps false positives out.

**This toggle only adds matches - it never removes tracking for anything.**
When it's off, a link on a configured scheduling domain doesn't just fail
to become `lumn_appointment_click` - detection falls through to the next
rule in the precedence chain (typically `lumn_external_link`), exactly
like any other link. Nothing "disappears" from tracking because a more
specific classification is turned off.

### Administrator classification settings

Three new textareas under **LUMN Utilities -> SEO & Tracking ->
Automatic Classification Settings** (`register/engagement-tracking.php`,
one option, `lumn_ut_tracking_classification_config`), one value per line
(commas also accepted):

| Setting | Feeds | Accepts |
| --- | --- | --- |
| Appointment URL Patterns | `appointment_url_patterns` | a leading-`/` path prefix, e.g. `/request-appointment/` |
| Appointment / Scheduling Domains | `appointment_domains` | a bare domain, e.g. `scheduler.example.com` |
| External Link Tracking: Excluded Domains | `external_link_excluded_domains` | a bare domain, e.g. `portal.example.com` |

All three are entirely optional. Pasting a full URL into a domain field
(e.g. `https://scheduler.example.com/book`) is handled gracefully - only
the host is kept. Every value is lowercased, trimmed, and deduped on save.
The Health Checker (below) flags an obviously-wrong entry (a pattern
missing its leading `/`, a "domain" containing a space) as a warning, not
an error, since it's a configuration nit, not a broken feature.

### Event delegation, no polling

Both the click-based features (download/external link) and video tracking
use the same delegated, capture-phase listener pattern as Step 2 - nothing
scans the DOM on a timer or via `MutationObserver`. A download or external
link inserted into the page after load (an AJAX-loaded block, a lightbox)
is tracked automatically the moment it's clicked, with no re-initialization
step; the same is true of a `<video>` element added later, since the
`play`/`timeupdate`/`ended` listeners are already attached to `document`.



Four read-only/diagnostic tools under `LUMN Utilities -> Tracking
Debugger`, none of which enable tracking, inject GTM/GA4, or talk to
anything outside this site.

### Front-end Tracking Debugger overlay

A small panel (`public/js/lumn-tracking-debugger.js`) that renders on the
site's front end, rendered inside a Shadow DOM root (`host.attachShadow()`)
so the host theme's CSS can never bleed into it and its own styles can
never leak out. It listens for the exact same `lumn:tracking:event` /
`lumn:tracking:suppressed` browser events the tracking core already
dispatches (Steps 1-2) - it doesn't change what gets dispatched or add any
new server-side event storage, it only visualizes what's already
happening. The Recent Events feed is a plain in-memory JS array, reset on
every page load; nothing is written to localStorage, a cookie, or any
server-side store to build it.

**Authorization** (`lumn_ut_debug_overlay_should_render()`,
`register/tracking-debugger.php`) is independent of the master/feature
toggles - it requires an authenticated user with the
`LUMN_UT_TRACKING_CAPABILITY` capability, full stop, regardless of any
query string. An already-authorized admin can activate it two ways:

- **Persistently**, via a nonce-verified `admin-post.php` action
  (`lumn_ut_toggle_debug_overlay`) that flips a per-user meta flag
  (`lumn_ut_debug_overlay_enabled`) - available from the Debugger admin
  tab or the overlay's own "Disable debugging" link.
- **For one page view**, via `?lumn_debug=1` - never persisted, and safe
  without its own nonce because it's not a state-changing action: it only
  decides whether to render extra HTML/JS for a viewer who has already
  passed the same capability check above.

The overlay script is only enqueued at all when
`lumn_ut_debug_overlay_should_render()` is true - a normal visitor's page
load never loads this file, attaches no listener, and renders no UI. This
is separate from, and does not require, the `debugger` *feature* toggle
(Step 1) being on - if it's off, the panel still renders (so an admin can
see that it's off) but its Recent Events feed stays empty, since nothing
is being dispatched for it to observe.

**Debugger event sources** (spec item 6): `pushEvent()` and
`consumeRelay()` both accept an optional `source` argument - a
human-readable string like `"Automatic phone detection"`, `"Explicit
data-lumn-event"`, or a provider label like `"Gravity Forms"` - purely for
this debug display, never part of the dataLayer payload itself. Automatic
clicks (`public/js/lumn-tracking-events.js`) and relayed form submissions
(`register/form-tracking.php`, via `lumn_ut_tracking_relay_event()`'s
`$source` param) all supply one.

**Safety**: the event detail view only ever reads keys the clicked
event's own registry entry declares in `params` - never arbitrary
properties off the raw browser event, even if something (a bug, a
tampered relay cookie) put extra keys there. Since every event that
reaches this UI already passed through the same
allowlist/denylist/scalar filtering as any other LUMN event before it was
dispatched, the debugger inherits those PII/PHI guarantees automatically
rather than needing its own.

### Test events

A "Test Event" tool lets an admin pick any registered event and send it
for real, through the normal `LumnTracking.pushEvent()` path - only
available when `window.LumnTracking` exists (i.e. Master Tracking is on;
otherwise the tool explains why it's unavailable instead of showing a
button that can't work). Every test event:

- requires an explicit button click, gated by a `confirm()` warning
  dialog naming the event and warning that an existing GTM container may
  still react to it - never sent automatically, never on page load;
- is always sent with `lumn_debug: true` (added to
  `lumn_ut_tracking_base_event_params()`, so every event type can carry
  it) and `source: 'Debugger Test Event'`, so it's unambiguous in both the
  data layer and the debugger's own feed that it wasn't a genuine
  interaction;
- goes through the real `dataLayer`/GTM pipeline, deliberately - renaming
  or otherwise hiding it from GTM's Custom Event trigger would make the
  debugger inaccurate for its actual purpose (previewing what a real event
  looks like), so the warning dialog is the safeguard, not event-name
  trickery;
- is never sent directly to GA4 or any other destination - it only ever
  reaches `window.dataLayer`, the same as any other LUMN event.

### Page scanner (current-page inspector)

The overlay's "Scan This Page" button walks the *current* page's DOM
(`document.querySelectorAll('a[href]')` / `'[data-lumn-event]'`) on
demand - never automatically, never across every page on the site - and
classifies each element with the same rules as
`public/js/lumn-tracking-events.js` (tel:/mailto:/known appointment or
maps URLs, and `data-lumn-event` resolution). This logic is deliberately
**re-implemented**, not imported from that script: the click-detection
script self-bails (attaches no listener, or isn't loaded at all when
Master Tracking is off) whenever nothing would actually fire, but the
scanner's whole purpose is to keep working in exactly that situation, to
help answer "why isn't this tracking." Keep both in sync if the
classification rules ever change. An unrecognized `data-lumn-event` value
is reported clearly (`⚠ Unknown LUMN event: "..."`) rather than silently
ignored, and is never fired.

### Event Catalog

`admin/tracking-debugger-page.php`'s Catalog tab renders directly from
`lumn_ut_tracking_event_registry()` - name, category, action, required
feature (and whether it's currently on), full parameter list, and a
recommended GTM trigger, with no data duplicated by hand. Each event is an
accordion item (the same `.lumn-utilites-admin-accordion` component used
elsewhere in this plugin), so nothing new needs to be learned to use it.

### GTM Guide and recipes

`lumn_ut_tracking_gtm_recipe( $event_key )` (`register/tracking-debugger.php`)
is the single source of truth for GTM setup guidance - trigger type
(always "Custom Event"), the exact event name to match, an optional
condition (currently only `lumn_form_submit`'s `lumn_form_type equals
...`, generated from `lumn_ut_tracking_form_type_registry()` rather than
hardcoded), and an optional GA4-mapping suggestion. Both the Event Catalog
tab and the dedicated GTM Guide tab render through the same
`lumn_ut_render_gtm_recipe()` function, so the guidance can never drift
between the two places it appears. Every "Copy" button
(`lumn_ut_render_copy_button()`, wired up in `admin/admin-scripts.js`)
copies one exact value - an event name or a condition value - so a
coworker can paste it straight into GTM.

GA4 mapping guidance follows docs/TRACKING.md's existing rule: lead-like
events (phone/appointment/directions/email clicks, and form submissions
of type appointment/contact/consultation) suggest `generate_lead`; an
employment-type form submission gets no GA4 suggestion at all, since a job
application isn't a patient lead. This is documentation only - LUMN
Utilities never creates or modifies anything in GTM or GA4.

### Tracking Health Checker

`lumn_ut_health_run_checks( $run_remote_checks )`
(`register/tracking-debugger.php`) runs a fixed set of checks and returns
`good` / `warning` / `error` / `info` results, each explicitly marked
`can_verify` true or false:

- **Always checked** (no network involved): Master Tracking and every
  implemented feature's current state (`info`, not `warning`/`error` -
  "off" is a normal, valid state, not a problem); structural validity of
  the event registry itself (an `error` here would only ever mean a
  future code change broke the registry, never a site admin's settings);
  a note on Data Layer availability; Gravity Forms/Formidable detection +
  whether their LUMN toggle is on; and, for each provider actually
  detected, any of its live forms with no LUMN tracking configured yet
  (exactly the `"Form 7 is detected but not configured"` example from the
  spec). Also (Step 5): any configured Appointment URL Pattern that
  doesn't start with `/`, or any configured domain (Appointment /
  Scheduling Domains, External Link Tracking: Excluded Domains) that
  doesn't look like a valid domain - reported as a `warning`, never an
  `error`, since this configuration is entirely optional and an empty
  config is never flagged.
- **Only checked when an admin clicks "Run Health Check"**
  (`admin/tracking-debugger-page.php`'s Health Check tab - never on tab
  load): a single `wp_remote_get( home_url( '/' ) )`, cached briefly
  (60 seconds) to avoid re-fetching on every click, used for two things -
  scanning the response body for a GTM container signature
  (`googletagmanager.com/gtm.js` or a `GTM-XXXXXXX` id), and for any
  `data-lumn-event="..."` attributes, each validated against
  `lumn_ut_tracking_resolve_event_key()` (mirrors the client-side
  resolution in `public/js/lumn-tracking-events.js`); and (Step 5), for
  whichever of Video/Download/External Link Tracking are currently
  enabled, a simple count of `<video>` elements / downloadable-file links /
  external links found on that one page. **This count is always reported
  as `good`, never as a warning or error, whether it's zero or not** - a
  feature having nothing to track on this one particular page is normal,
  useful context, not a problem (finding zero `<video>` elements on a page
  that isn't expected to have one is not "broken"). This check is skipped
  entirely for a feature that's off, since its disabled state is already
  covered by the feature-toggle loop above it.

**This fetch always targets this site's own front page - never LUMN's
servers, never any third party.** It is the one place in this whole
system that makes an HTTP request at all, and it's a local self-check, on
demand, not a remote tracking/collection service.

**What LUMN can and cannot verify** - stated explicitly in the UI, not
just here: finding a GTM container is a reasonably solid "yes"
(`can_verify: true`); *not* finding one is a much weaker signal
(`can_verify: false`) - GTM might load conditionally, only on other pages,
or the fetch itself might have failed for an unrelated reason - so it's
reported as a `warning`, never an `error`, and never phrased as "GTM is
broken." **LUMN Utilities can confirm a GTM container appears to be
installed. It cannot determine whether a specific GTM trigger or tag is
actually configured to consume LUMN events** - that lives entirely inside
GTM's own configuration, which this plugin has no access to and does not
attempt to inspect.

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

### Form tracking (Step 3)

Requires a test form on a Gravity Forms or Formidable Forms install.

1. Re-enable the master switch, then check **Form Tracking**, then (under
   Form Tracking Providers) check tracking for whichever plugin you have -
   confirm it shows **Detected** first. Save Changes.
2. Scroll to **Tracked Forms**, find your test form, set **Type** (e.g.
   `contact`), check **Tracking: Enabled** for that one form only, leave a
   second form unchecked. Save Changes.
3. On the front end, open the console and run `window.dataLayer` - confirm
   it's `undefined`/empty.
4. Submit the tracked test form with valid data.
5. Run `window.dataLayer` again - the last entry should look like:
   ```json
   { "event": "lumn_form_submit", "lumn_event_category": "lead", "lumn_event_action": "form_submit", "lumn_form_id": "3", "lumn_form_name": "...", "lumn_form_type": "contact", "lumn_form_provider": "gravity_forms" }
   ```
6. Confirm there is **no** field you typed into the form (name, email,
   message, etc.) anywhere in that object.
7. Submit the form again but trigger a validation error (leave a required
   field blank) - confirm `window.dataLayer` does not grow.
8. Submit the *second*, untracked form - confirm `window.dataLayer` does
   not grow.
9. If your form's confirmation is a redirect to a different page/thank-you
   URL: after submitting, check `window.dataLayer` **on that destination
   page** - the event should appear there (it's relayed via a short-lived
   cookie, not the original request).
10. Enable **Debugger** and resubmit - the console should show
    `[LUMN Tracking] Event detected: lumn_form_submit` with Provider/Form
    Type/Form ID/Form Name (and Location, if you assigned one) - never
    field values.
11. Uncheck Form Tracking (or the specific form) and confirm submissions
    stop producing events.
12. Confirm nothing else changed: any existing GTM/GA4 tags on this site
    still behave exactly as before - LUMN only added a new dataLayer
    event for GTM to optionally react to.

### Debugger, catalog, health checker, GTM guide (Step 4)

1. Log out (or open a private window) and confirm a normal visitor sees
   no debugger UI anywhere, and that `?lumn_debug=1` on any page does
   nothing for them either.
2. Log back in as an administrator. `LUMN Utilities -> Tracking Debugger`
   - confirm Status shows **● Debugging OFF**.
3. Click **Enable Debugging**, then visit any front-end page - a small
   panel should appear in the bottom-right corner.
4. With Master Tracking + Debugger + Phone Click Tracking all on (see the
   earlier click-tracking procedure), click a `tel:` link - it should
   appear in the panel's Recent Events within a moment, and clicking that
   entry should expand Category/Action/Source/params - never a phone
   number.
5. Click an appointment CTA and submit a tracked test form - both should
   appear the same way, the form entry showing Provider/Form ID/Form
   Name/Form Type.
6. In the panel, use **Scan This Page** - confirm it lists the elements
   you just interacted with, each with an Event and a Source (Automatic
   or Explicit).
7. Temporarily add `data-lumn-event="not_a_real_event"` to any element's
   markup, reload, scan again - confirm it's reported as an unrecognized
   event rather than silently ignored (and that nothing fired for it).
8. Use the **Test Event** dropdown to pick an event, click **Send Test
   Event** - confirm the browser `confirm()` warning appears, that
   declining sends nothing, and that confirming adds an entry to
   `window.dataLayer` with `lumn_debug: true`.
9. Back in wp-admin, open the **Event Catalog** tab - confirm every
   implemented event appears, expand one, and click a **Copy** button;
   paste somewhere to confirm the exact event name was copied.
10. Open the **GTM Guide** tab - confirm the architecture explanation and
    a recipe per event, including the `lumn_form_type equals ...`
    condition under the form submission recipe.
11. Open the **Health Check** tab, click **Run Health Check** - confirm
    results appear only after clicking (not automatically), that
    everything you've enabled shows "Good", and that GTM detection is
    labeled with what LUMN can/cannot verify.
12. Disable one enabled form's tracking (or leave a detected form
    unconfigured) - re-run the Health Check and confirm it's flagged as a
    warning ("detected but not configured"), not an error.
13. Click **Disable Debugging** - confirm the front-end panel no longer
    appears on reload, and that this alone didn't change any Master
    Tracking / feature toggle settings.

### Engagement, download, video, and CTA tracking (Step 5)

1. With Master Tracking on and Debugger on, check **Download Tracking**
   -> Save Changes. On a page with a link to a `.pdf`, open the console,
   click it - confirm `[LUMN Tracking] Event detected: lumn_file_download`
   with `lumn_file_type: "pdf"` and `lumn_file_name` equal to just the
   file's own name (no path, no query string).
2. Check **External Link Tracking** -> Save Changes. Click a link to a
   different domain (not the PDF from step 1, and not the domain you'll
   configure in step 6) - confirm `lumn_external_link` fires with only
   `lumn_external_domain` (a bare hostname, never a full URL).
3. Click the same `.pdf` link from step 1 again - confirm it still fires
   only `lumn_file_download`, never additionally `lumn_external_link`,
   even if that PDF happens to be hosted off-site.
4. Add `data-lumn-track="false"` to the wrapper of any external or
   download link, reload, click it - confirm nothing fires. Add an
   explicit `data-lumn-event="phone_click"` to that same suppressed
   element - confirm it now fires normally (suppression never blocks an
   explicit tag).
5. Check **Video Tracking** -> Save Changes. On a page with an actual
   `<video>` element (not the SU-lightbox `.mp4`-in-iframe pattern - see
   docs above for why that one can't be tracked), press play - confirm
   `lumn_video_start` fires once, with `lumn_video_provider: "html5"` and
   no source URL anywhere in the payload.
6. Pause and resume the same video - confirm no second `lumn_video_start`
   fires. Let it play to at least the 25% mark - confirm
   `lumn_video_progress` fires with `lumn_video_percent: 25` (and again at
   50/75 if you let it keep playing), each exactly once. Let it finish -
   confirm `lumn_video_complete` fires once. Play it again from the start
   - confirm a *new* `lumn_video_start` fires.
7. Under **Automatic Classification Settings**, add a path (e.g.
   `/request-appointment/`) to **Appointment URL Patterns** and a domain
   (e.g. a real scheduling widget's domain, if this site uses one) to
   **Appointment/Scheduling Domains** -> Save Changes. Check **Automatic
   CTA Classification** -> Save Changes. Click a link matching either
   configured rule - confirm `lumn_appointment_click` fires (not
   `lumn_external_link`), with no confidence score or extra metadata in
   the payload.
8. Uncheck **Automatic CTA Classification** only (leave the domain/pattern
   settings in place) -> Save Changes -> reload. Click that same
   scheduling-domain link again - confirm it now falls through to
   `lumn_external_link` instead of firing nothing - configuring a pattern
   never means a link "disappears" from tracking entirely, only that its
   more specific classification is off.
9. Add a domain to **External Link Tracking: Excluded Domains**, reload, click a link to
   that domain - confirm no `lumn_external_link` fires for it.
10. Open the **Health Check** tab and run it. With Download/External
    Link/Video Tracking all enabled, confirm each shows **Good** with a
    count of what it found on the front page (or "no elements found... not
    necessarily a problem" if the front page doesn't have one) - never a
    warning purely for finding zero. Temporarily enter
    `no-leading-slash` (missing the `/`) into Appointment URL Patterns,
    re-run - confirm it's flagged as a warning, not an error.
11. Insert a download link and an external link into the page dynamically
    (e.g. via the browser console:
    `document.body.insertAdjacentHTML('beforeend', '<a href="/test.pdf">t</a>')`)
    and click the newly-inserted link - confirm it still fires correctly
    with no page reload or re-initialization needed.
12. Uncheck the master switch - confirm no further downloads, external
    links, video plays, or CTA clicks push anything, and that (after a
    fresh page load) no `lumn-tracking-video.js` script tag is present in
    the page source when Video Tracking is off.
