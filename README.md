# LUMN Utilities

A set of custom shortcodes and tools for LUMN sites.

> This `README.md` exists on the `test` branch only. It documents the
> development/test workflow for this branch and is intentionally not merged
> into `main`, which remains the production source tracked by the plugin's
> update checker.

## Features

- **Shortcodes & practice data** — site name, address, phone, hours, social
  links, and related shortcodes for a dental/medical practice site, backed by
  a single-practice options set or, when configured, multiple **Practice
  Locations** (`register/locations.php`, `LUMN Utilities -> Practice
  Locations`).
- **LUMN Tracking / SEO Tools** — an opt-in, standardized event-tracking layer
  (`LUMN Utilities -> SEO & Tracking` and `-> Tracking Debugger`). Every
  feature defaults to off on install/update; nothing is ever sent anywhere
  outside a `window.dataLayer` push already on the site, and LUMN never
  creates or modifies a GTM container/tag or a GA4 property itself. Currently
  covers:
  - Phone, email, SMS, appointment, and directions click tracking, plus an
    explicit `data-lumn-event` markup mechanism.
  - Form submission tracking (Gravity Forms, Formidable Forms) with optional
    Practice Location association.
  - Download, external-link, and native-`<video>` engagement tracking, and
    conservative automatic CTA (appointment) classification.
  - A front-end Tracking Debugger overlay, Event Catalog, and Health Checker.
  - A central configuration Dashboard with a plain-language summary of what's
    currently tracked, per-event overrides, global URL exclusions, safe
    reset, and JSON config export/import between LUMN sites.

  See **`docs/TRACKING.md`** for the full developer guide (architecture,
  event spec, PII/PHI rules, GTM setup guidance) and an administrator's
  guide for someone who doesn't know GTM.

## Test deployment pipeline

Pushing to `test` automatically deploys this repository's `test` branch to a
dedicated Kinsta test WordPress environment via GitHub Actions
(`.github/workflows/deploy-test.yml`). Production (`main`) and the existing
LUMN update infrastructure are completely separate and unaffected by this
pipeline.

```
Local development
      |
      | git push origin test
      v
   GitHub Actions (deploy-test.yml)
      |
      | SSH
      v
Kinsta test WordPress site
      |
      v
wp-content/plugins/lumn-utilities/
```

### Day-to-day workflow

```bash
git checkout test
git pull origin test

# ...make changes...

git add .
git commit -m "Description of change"
git push origin test
```

Pushing to `test` triggers the `Deploy to Kinsta Test Environment` GitHub
Actions workflow automatically. It SSHes into the Kinsta test environment,
runs `git fetch origin test` and `git reset --hard origin/test` inside
`~/public/wp-content/plugins/lumn-utilities/`, and fails the workflow run if
anything goes wrong. Once the workflow finishes (usually well under a
minute), refresh the test WordPress site to see the change.

### Notes

- This workflow only runs on pushes to `test`. It never runs on `main` and
  never modifies production.
- The Kinsta test environment's plugin directory is a git checkout tracking
  `test`, kept in sync with a hard reset on every deploy — do not make
  untracked edits directly on the server, they will be overwritten.
- If a PHP change doesn't appear after deploying, see the caching notes in
  the pipeline setup report (OPcache / object cache on the test site).
