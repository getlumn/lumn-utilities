# LUMN Utilities

A WordPress plugin providing shortcodes, a multi-location practice data
model, and a REST API for LUMN sites (dental/medical practice websites).

## What it does

- **Practice info shortcodes** — name, phone, text number, fax, email,
  address, Google Maps embed, hours, and social/link URLs (Facebook,
  Google, Instagram, LinkedIn, Pinterest, Threads, TikTok, X, Yelp,
  YouTube, plus Appointments and Payments links), each with a matching
  admin settings field under **LUMN Utilities** in the WordPress admin menu.
- **Multi-location support** — a **Practice Locations** admin screen for
  sites with more than one physical location. Every practice-info
  shortcode accepts an optional `location="slug-or-id"` attribute; leaving
  it off resolves to the primary location. Sites with no locations
  configured keep using the original site-wide settings with no changes
  required.
- **Per-location link overrides** — any of the social/link URLs can be
  overridden for a specific location (e.g. a different Google Business
  Profile per office), both via the `[lumn_social_url]` shortcode's
  `location` attribute and via `/lumn-social-url-{name}/{location}`
  redirects.
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
index.php                  Plugin bootstrap, asset enqueueing, update checker
register/
  functions.php             Shared helpers, admin menu, branded header
  field-registry.php         Single source of truth for practice-data settings
  fields.php / sections.php  Settings API registration for the shortcode fields
  shortcodes.php             All [lumn_*] shortcodes
  redirects.php               /lumn-social-url-{name} redirects
  locations.php               Practice Locations data layer + admin_post handlers
  rest.php                    lumn/v1 REST API
  legacy-compat.php           Predecessor-plugin shortcode/option compatibility
  legacy-blocks.php           Predecessor-plugin Gutenberg block compatibility
admin/
  locations-page.php         Practice Locations admin screen
  admin-styles.css / admin-scripts.js
blocks/                      Reimplemented legacy custom blocks (compat only)
patterns/                    Reimplemented legacy block patterns (compat only)
slick/                       Bundled Slick carousel assets (used by a legacy block)
```

## Updates

This plugin ships updates via the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
library, pointed at this repository's `main` branch and GitHub Releases.
Sites running this plugin check `main` for new tagged releases
automatically - **`main` is production**. Development and testing happen
on the `test` branch, which deploys to a dedicated test WordPress
environment via GitHub Actions and never touches `main` or production
sites.

## Contributing

Match the existing procedural style (no classes), the `lumn_ut_` function
prefix, and the `Lumn\Utilities` namespace. Keep changes additive where
possible - existing shortcodes, options, and REST routes should keep
working exactly as before for sites that haven't opted into new fields.
