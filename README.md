# LUMN Utilities

A WordPress plugin providing shortcodes, a multi-location practice data
model, an opt-in event-tracking/SEO layer, per-site developer
documentation tooling, and a REST API for LUMN sites (dental/medical
practice websites).

## What it does

- **Practice info shortcodes** — name, phone, text number, fax, email,
  address, Google Maps embed, hours, and social/link URLs (Facebook,
  Google, Instagram, LinkedIn, Pinterest, Threads, TikTok, X, Yelp,
  YouTube, plus dedicated Google Maps/Reviews/Write-a-Review,
  Appointments, and Payments links), each with a matching admin settings
  field under **LUMN Utilities** in the WordPress admin menu.
- **Multi-location support** — a **Practice Locations** admin screen for
  sites with more than one physical location. Every practice-info
  shortcode accepts an optional `location="slug-or-id"` attribute, or
  falls back to a `?location=slug-or-id` query parameter on the page URL
  when the attribute is left off; leaving both off resolves to the
  primary location. Sites with no locations configured keep using the
  original site-wide settings with no changes required. Dedicated
  `[lumn_location_name]` (internal label) and `[lumn_practice_name]`
  (patient-facing name) shortcodes read a location's own name fields.
- **Per-location link overrides** — any of the social/link URLs can be
  overridden for a specific location (e.g. a different Google Business
  Profile per office), via the `[lumn_social_url]` shortcode's `location`
  attribute or the `?location=` query parameter, and via
  `/lumn-social-url-{name}/{location}` path segments or `?location=` on
  the plain `/lumn-social-url-{name}/` redirect endpoints.
- **LUMN Tracking / SEO Tools** — an opt-in, standardized event-tracking
  layer under **LUMN Utilities → SEO & Tracking**. Every feature defaults
  to off on install/update; nothing is ever sent anywhere outside a
  `window.dataLayer` push already on the site, and LUMN never creates or
  modifies a GTM container/tag or a GA4 property itself. Covers:
  - Phone, email, SMS, appointment, and directions click tracking, plus
    an explicit `data-lumn-event` markup mechanism and configurable
    anchor/fragment and global URL exclusions.
  - Form submission tracking (Gravity Forms, Formidable Forms) with
    optional Practice Location association.
  - Download, external-link, and native `<video>` engagement tracking,
    and conservative automatic CTA (appointment) classification.
  - A Dashboard tab with a plain-language summary of what's currently
    tracked, presets, per-event overrides, safe reset, and JSON config
    export/import between LUMN sites.
  - Debugger, Event Catalog, Health Check, and GTM Guide tabs on the same
    page — a front-end debug overlay visible only to an authorized,
    explicitly opted-in administrator, the full event registry, a
    configuration health checker, and deterministic GTM trigger recipes
    for every event.

  See `docs/TRACKING.md` for the full developer guide.
- **Developers tab** — per-site technical context visible only to a
  `company_super_admin`-capability role, under **LUMN Utilities →
  Developers**: a Site Profile (client/contract info, plus
  auto-detected WordPress/PHP/theme version, nameservers, and
  registrar), free-form Site Notes, a live Dependencies table (installed
  plugins plus manually tracked non-plugin dependencies, with licence
  ownership/expiry), a Known Issues tracker, and a chronological
  Activity Log.
- **`lumn/v1` REST API** — read/write access to practice settings and
  locations for external tooling (e.g. a site-builder service), gated by
  a dedicated `lumn_manage_site_data` capability. See `register/rest.php`
  for the full route list.
- **Backward compatibility** — this plugin is a drop-in replacement for
  two predecessor plugins (`lumn-utilities-2` and the older "DCMO
  Utilities" plugin). Either one can be deactivated and this plugin
  activated in its place without breaking existing shortcodes, links, or
  page content built with custom blocks from the older plugin. See
  `register/legacy-compat.php` and `register/legacy-blocks.php` for
  details - none of this is surfaced in the admin UI, it exists purely to
  keep old content working.

## Structure

```
index.php                    Plugin bootstrap, asset enqueueing, update checker
docs/
  TRACKING.md                  Full developer guide for LUMN Tracking / SEO Tools
register/
  functions.php                Shared helpers, admin menu, branded header
  field-registry.php           Single source of truth for practice-data settings
  fields.php / sections.php    Settings API registration for the shortcode fields
  shortcodes.php                All [lumn_*] shortcodes
  redirects.php                 /lumn-social-url-{name} redirects
  locations.php                 Practice Locations data layer + admin_post handlers
  rest.php                      lumn/v1 REST API
  legacy-compat.php             Predecessor-plugin shortcode/option compatibility
  legacy-blocks.php             Predecessor-plugin Gutenberg block compatibility
  tracking-registry.php         Tracking feature-flag API + event registry
  tracking.php                  Tracking settings registration + safe data-layer abstraction
  tracking-config.php           Central config model, presets, reset, export/import
  tracking-debugger.php         Debug overlay activation, health checker, GTM recipes
  form-tracking.php             Gravity Forms / Formidable form-submission tracking
  engagement-tracking.php       Download/external-link/video/CTA classification config
  dev-notes.php                 Developers tab data layer (profile, notes, dependencies, issues, log)
  dependency-defaults.php       Centralized Dependencies-table defaults, deployed via git
admin/
  locations-page.php           Practice Locations admin screen
  tracking-page.php             SEO & Tracking admin page (Dashboard/Configure/Debugger/
                                 Catalog/Health/GTM Guide/Import-Export tabs)
  tracking-debugger-page.php    Debugger/Event Catalog/Health Check/GTM Guide tab rendering
  dev-notes-page.php            Developers admin page
  admin-styles.css / admin-scripts.js
public/js/
  lumn-tracking.js               Core phone/email/appointment/directions click tracking
  lumn-tracking-events.js        Download/external-link/video classification
  lumn-tracking-forms.js         Form-submission relay
  lumn-tracking-video.js         Native <video> engagement tracking
  lumn-tracking-debugger.js      Front-end debug overlay panel
blocks/                      Reimplemented legacy custom blocks (compat only)
patterns/                    Reimplemented legacy block patterns (compat only)
slick/                       Bundled Slick carousel assets (used by a legacy block)
```

## Updates

This plugin ships updates via the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
library, pointed at this repository's `main` branch and GitHub Releases.
Sites running this plugin check `main` for new tagged releases
automatically - **`main` is production**. Development happens on the
`test` branch before being merged into `main`.

## Contributing

Match the existing procedural style (no classes), the `lumn_ut_` function
prefix, and the `Lumn\Utilities` namespace. Keep changes additive where
possible - existing shortcodes, options, and REST routes should keep
working exactly as before for sites that haven't opted into new fields.
