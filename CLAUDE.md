## Taktician

**MUST read [.taktician/instructions/skill-must-read.md](.taktician/instructions/skill-must-read.md)** before any block or skill work, and after context compaction.

---

## SSH / production access — hard rules

SSH access to `ignitestudentu.ssh.wpengine.net` exists for **one purpose only**:
creating and editing the custom plugin(s) under
`wp-content/plugins/<custom-plugin>/`, and only when explicitly asked.

**Never, over SSH:**

- Delete **any** file. Not core, not plugins, not themes, not uploads.
- Touch WordPress core (`wp-admin/`, `wp-includes/`, `wp-config.php`, `index.php`,
  any `wp-*.php`), the site root, or `.htaccess`.
- Edit **any** other plugin's files, including The Events Calendar / Event
  Aggregator, or anything else under `wp-content/plugins/` that we did not author.
- Edit theme files. The theme is this repo — theme changes go through
  commit + push here, never a direct server edit.

Work stays inside the custom plugin's own folder. If a task appears to require
anything outside it, stop and ask first.

### Deploying

- **`origin`** (GitHub, `IGNITE-Student-Union/ign`) — where theme commits go.
- **`production` / `staging`** (`git.wpengine.com`) — **do not push.** These
  remotes deploy to the **WordPress root**, not to `wp-content/themes/ign/`.
  Ask how the theme is actually deployed rather than assuming these are the path.

### Incident — 2026-08-09: theme repo deployed to the WordPress root

`git push production main` was run to deploy a theme change. WP Engine's
`production` remote deploys the pushed tree to the **site root**, so the entire
theme repo (`inc/`, `blocks/`, `functions.php`, `style.css`, …) landed at
`/nas/content/live/ignitestudentu/` alongside `wp-admin/` and `wp-config.php`.

WordPress then loaded two copies of every theme file and died with:

```
PHP Fatal error: Cannot redeclare function render_policy_sort_order_meta_box()
(previously declared in /nas/content/live/ignitestudentu/inc/post-types/policy-order.php:17)
in .../wp-content/themes/ign/inc/post-types/policy-order.php on line 17
```

The site threw a critical error for roughly an hour. Recovery required deleting
34 wrongly-placed entries from the site root — exactly the kind of destructive
SSH operation the rules above now forbid, and it was only survivable because the
repo contains no `index.php` or `wp-*` files, so core was never overwritten.

**Root cause:** assuming a configured git remote was a theme-deploy target
without verifying where it deploys to.

**Rules that follow from it:**

1. Do not push to the `production` or `staging` remotes from this repo.
2. Verify what a deploy target does *before* the first push to it, not after.
3. A deploy that "succeeds" (exit 0) is not a deploy that is correct — check the
   result on the server, and check that the **rest** of the site still loads.
4. Treat anything outside the custom-plugin folder as read-only over SSH.
