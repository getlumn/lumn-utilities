# LUMN Utilities

A set of custom shortcodes and tools for LUMN sites.

> This `README.md` exists on the `test` branch only. It documents the
> development/test workflow for this branch and is intentionally not merged
> into `main`, which remains the production source tracked by the plugin's
> update checker.

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
