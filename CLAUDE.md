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

### Deploying — push to `origin`, nothing else

**`git push origin main` IS the deploy.** `.github/workflows/deploy.yml` runs on
every push to `main`: it builds assets (`npm run build`) and rsyncs to the
correct paths on WP Engine via the `wpengine/github-action-wpe-site-deploy`
action.

| Repo path | Deploys to |
| --- | --- |
| everything except `custom-plugins/` | `wp-content/themes/ign/` |
| `custom-plugins/myignite-event-importer/` | `wp-content/plugins/myignite-event-importer/` |

Both are incremental rsyncs: unchanged files are not transferred, and
`--delete` only removes files that no longer exist in the repo.

- **`production` / `staging`** (`git.wpengine.com`) — **never push to these.**
  They deploy the pushed tree to the **WordPress root**, not to the theme
  directory. They are redundant anyway: the GitHub Action already handles
  deployment. See the incident below.
- Custom plugins we author belong in `custom-plugins/<plugin>/` and reach the
  server through the Action — **not** by writing files over SSH.
- WordPress core and third-party plugins are deliberately **not** in this repo.
  WP Engine and WordPress update those themselves; versioning them here would
  mean fighting those updates. They are covered by WP Engine's backups.

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
without verifying where it deploys to. The push was also completely
unnecessary — `.github/workflows/deploy.yml` had already deployed the same
commit to the correct path when it was pushed to `origin`. Checking how
deployment actually worked would have prevented the whole incident.

**Rules that follow from it:**

1. Do not push to the `production` or `staging` remotes from this repo.
2. Verify what a deploy target does *before* the first push to it, not after.
3. A deploy that "succeeds" (exit 0) is not a deploy that is correct — check the
   result on the server, and check that the **rest** of the site still loads.
4. Treat anything outside the custom-plugin folder as read-only over SSH.
