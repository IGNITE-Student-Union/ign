# MyIGNITE Event Importer — operations notes

Imports events from MyIGNITE (CampusGroups) into The Events Calendar.
Replaced the Event Aggregator ICS pipeline on 2026-08-08, then switched from
CampusGroups' Data Export API to its Data API (`rss_events`) on 2026-08-28 —
see [Data sources](#data-sources).

## Where this code lives, and how to change it

The source of truth is the **`ign` theme repo**, at:

```
custom-plugins/myignite-event-importer/
```

It deploys to `wp-content/plugins/myignite-event-importer/` on WP Engine via
`.github/workflows/deploy.yml`, on every push to `main`.

**To make a change:** edit the files in the repo, commit, and push to `main`.
The GitHub Action deploys it, the same way theme changes are deployed.

> **Do not edit these files directly on the server over SSH.** The deploy is an
> rsync with `--delete`, so the repo is authoritative — a server-side edit is
> silently reverted by the next push, and the change is lost with no record
> that it ever existed.

`custom-plugins/` is excluded from the theme's own rsync, so this folder is not
also copied into `wp-content/themes/ign/` (where WordPress would never load it).

## Settings screen

**Events → MyIGNITE Importer** (or the *Settings* link on the Plugins screen).

### Groups to import from

Tick the CampusGroups groups whose events should appear on the website, then
click **Save settings**. Unticking a group stops its events being imported or
updated from the next run onward. Events from that group already on the website
stay published and are simply no longer managed — see
[What happens to an event already on the website](#what-happens-to-an-event-already-on-the-website)
for the full removal policy.

**Check for other groups** asks CampusGroups for groups that are not already in
your list and shows them as extra checkboxes. Tick any you want and click
**Save settings**; they then join the permanent list above. This only runs when
clicked (and caches for 15 minutes) so the list of active groups stays visible
without repeatedly calling the CampusGroups API.

Ticking zero groups is allowed and respected — nothing will import until at
least one is ticked again. The screen warns when you do this.

### Run an import now

Choose the range of **event dates** to import, then click **Run import now**.
**Preview (dry run)** reports what would change without writing anything.

| Field | Default | Meaning |
|---|---|---|
| Start date / time | today, 00:00 | Earliest event start to import |
| End date / time | *(blank)* | Blank = all upcoming events, no upper bound |
| Both dates inclusive | ticked | Include events falling exactly on the boundaries |

All times are **Toronto time**. Daylight saving is applied automatically via
`DateTimeZone('America/Toronto')` — 6:00 PM is 6:00 PM in both EDT and EST, and
there is nothing to adjust twice a year. Administrators never deal with UTC.

Leaving both dates at their defaults does exactly what each scheduled run does.

## What runs, and when

| What | When |
|---|---|
| `myignite_event_sync_event` (WP-Cron) | Five times a day — **10 AM, 12 PM, 2 PM, 4 PM, 6 PM Toronto** |
| `wp myignite sync-events` | On demand (add `--dry-run` to preview) |
| **Run import now** button | On demand, from the settings screen |

Log: `wp-content/myignite-event-sync.log` (blocked from public HTTP access).

**Upcoming events only.** Every run imports events starting **today or later**.
Past events are never created or modified, and — importantly — an event that
merely ages into the past is *not* trashed; it is filtered out before any
create/update/trash decision is made, so it simply stays as it is.

**Freshness:** the Data API answers from CampusGroups' live database directly
— confirmed live (create an event, query seconds later, it's already there).
Unlike the old Data Export API (used until 2026-08-28), there is no batch job
lag to wait out. The sync runs five times a day (10 AM, 12 PM, 2 PM, 4 PM and
6 PM Toronto), so a club's edits usually reach the website within about two
hours during the day; the evening slot still sweeps up the full day.

The hand-authored-adoption workflow below still works and is still the
fastest path if something needs to be live before the next scheduled run:
create the event by hand in wp-admin using the **exact same title and start
time** as the CampusGroups event. The next sync adopts that post instead of
creating a duplicate, and CampusGroups becomes the source of truth for it
from then on.

## API credentials, and rotating them

The school code and API secret are **not** stored in the database or on the
settings screen. They are PHP constants in a file kept outside this plugin:

```
wp-content/mu-plugins/myignite-secrets.php

define( 'MYIGNITE_CG_SCHOOL_CODE', '...' );
define( 'MYIGNITE_CG_API_SECRET',  '...' );
```

This file is the **one deliberate exception** to the "edit in the repo, never on
the server" rule above: it holds live credentials, so it is intentionally kept
out of version control and off GitHub, and exists only on the server.

If CampusGroups rotates the secret, edit that file over SFTP/SSH and paste the
new value. Nothing needs changing in the plugin and no redeploy is required.
The settings screen shows whether the constants are present (it cannot tell
whether CampusGroups still *accepts* them — only a real run reveals that).

### What happens if the secret is rotated and not updated

The plugin degrades quietly and safely rather than breaking anything:

1. **It never fatals.** All API access returns `WP_Error`; a rejected secret is
   an HTTP 401/403 that is logged and ends the run. No other part of the site
   is in that code path, and the admin screen still loads.
2. **It never destroys data.** The run aborts *before* touching any post. This
   matters: an empty or failed response must never be read as "CampusGroups
   deleted everything", which would trash the whole calendar on a transient
   outage. Events already on the website are untouched.
3. **It retries by design.** Because each run queries a wide window and filters
   by event date, a failed run costs nothing — the next run re-covers exactly
   the same ground once the key is fixed.
4. **It tells you.** The failure is stored and shown as a dismissible red
   notice on the Events screens, with a hint pointing at the secrets file when
   the error looks like an auth rejection. A later successful run clears the
   notice automatically, so a fixed problem stops nagging on its own.

## Data sources

- **Events, groups, images — all one call** — `https://my.ignitestudentunion.ca/rss_events`,
  CampusGroups' **Data API**. A single synchronous `GET`, auth via a plain
  `X-CG-API-Secret` header (the same header/value the old Data Export API
  used). Answers from CampusGroups' live database directly — no batch layer,
  no polling. Returns XML, one `<item>` per event, parsed by
  `myignite_importer_data_api_item_to_array()`.
- Used until 2026-08-28: the **Data Export API**
  (`https://<school>.service.campusgroups.com/data/v1/events`, async:
  POST returns a `queryId`, GET polls until 200) plus a separate,
  undocumented `mobile_ws/v17/mobile_events_list` call just for images (the
  Data Export API had no image field at all). Replaced because that API is
  fed by a batch job on CampusGroups' side, confirmed at roughly 18–30 hours
  behind live — see git history on this file for what that pipeline looked
  like if you ever need to compare.
- **Groups** — the Data API has no standalone "list all groups" resource
  (the old `/groups` endpoint doesn't carry over). *Check for other groups*
  instead derives the list from `groupId`/`group` on whatever the events
  feed currently returns — a group with no recent/upcoming events won't
  appear until it has one.

### Images

The Data API hands back `eventOriginalPhotoFullUrl` directly — the true
original upload. No resize-variant guessing needed (the old `mobile_ws` feed
only ever reported a downscaled `r2_` copy, which the now-retired
`myignite_importer_image_url_candidates()` worked around).

## Which events get published

An event is imported only when **all** of these are true on CampusGroups:

| Requirement | Field |
|---|---|
| Hosted by a ticked group | `groupId` |
| Approved | `approvalStatus = 1` (strict allow-list — see below) |
| Not a draft | `draft = 0` |
| Not deleted | `eventDelete = 0` |
| **Publicly visible** | `publishCalendar = 0` ("Everyone") |
| Starts today or later | `eventStartDateTime` |

`approvalStatus` has no documented value legend beyond `1 = approved`
(confirmed repeatedly against real events). Rather than guess what other
codes mean, anything except `1` — a code never seen before, or the field
missing — is treated as not approved. If a genuinely new code ever turns up
in production, it's logged as a `NOTE:` line in the sync log the first time
it happens.

The visibility rule matters most often in practice. An event set to
**See Event on Calendar → No one** is hidden from CampusGroups' own public
events list, so it is not something to publish on the public website.

If an event should appear on the website but does not, check that setting first;
it is the usual cause. Changing it on CampusGroups is enough — the next run
picks the event up, image included, with no code change.

### What happens to an event already on the website

The importer draws a hard line between *"CampusGroups says this event is gone"*
and *"we chose not to manage this event"*. Only the first one removes anything.

| Change | Effect on the existing post |
|---|---|
| Deleted on CampusGroups | → **Trash** |
| Moved back to draft on CampusGroups | → **Trash** |
| Approval revoked on CampusGroups | → **Trash** |
| Its group is unticked in settings | **left published**, no longer updated |
| *See Event on Calendar* switched off | **left published**, no longer updated |
| Event date passes (becomes a past event) | **left published**, never touched again |

Removals are always to **Trash**, never a hard delete, and the log names the
exact reason.

The reasoning: a change *we* make — unticking a group, or CampusGroups hiding
an event from its own calendar — should never silently delete content an editor
can see on the site. Those events simply stop being managed; deleting them
stays a human decision. Only the source of truth saying "this event is
cancelled or unpublished" removes it automatically.

An event that stopped being managed and later qualifies again is picked back up
and updated as normal, with no duplicate created — matching is by CampusGroups
event ID, which never changes.

> Failing open by design: if `publishCalendar` is missing from the API
> response entirely — e.g. CampusGroups renames the field — events are treated
> as visible rather than hidden. Failing closed would let one upstream schema
> change trash the entire calendar in a single run.

An event with no image usually means one of:

- its **See Event on Calendar** setting is *No one* on CampusGroups, so it
  never reaches this importer at all — the fix is on CampusGroups, not here;
  or
- nobody uploaded a photo to the event.

A small image (e.g. 474×237) means that is genuinely what was uploaded to
CampusGroups. Re-upload a larger photo there.

## Gotchas worth knowing

- **Never use `WP_Query` / `wp post list` for `tribe_events` here.** TEC's
  Custom Tables V1 substitutes occurrence pseudo-IDs, so writes against those
  IDs silently affect zero rows. `wp post list` once reported 9 events when
  there were really 48. Query `wp_posts` directly.
- Deleting events with `wp_delete_post()` leaves rows behind in
  `wp_tec_occurrences` / `wp_tec_events`; they need clearing separately.
- Every run pulls the full events feed and filters by event date in code
  (`myignite_sync_events()`), rather than asking the API to scope the pull
  itself. An earlier version relied on an incremental "changed since last
  run" window; that silently did nothing when the change was on *our* side
  (e.g. images deleted locally). The Data API has no equivalent windowing
  concern in the first place — it isn't a batch snapshot — but the
  wide-pull-then-filter pattern is kept for the same reason: it can't skip
  something permanently just because one run had a problem.
- `MYIGNITE_IMPORTER_MAX_PER_RUN` truncating a run no longer advances anything
  that could cause events to be skipped permanently.

## Rollback

1. Deactivate this plugin — its deactivation hook unschedules the cron. Events
   already on the website keep rendering normally; nothing in the theme calls
   this plugin. If the admin screen is ever unreachable, run
   `wp plugin deactivate myignite-event-importer` over SSH.
2. To restore the old ICS pipeline: set the 6 Aggregator records' `post_status`
   back from `draft` to `tribe-ea-schedule` (IDs recorded in
   `wp-content/myignite-ics-rollback-state.json`), then uncomment the lines
   marked `DISABLED` in the theme's `inc/helpers/myignite-image-sync.php`.
   That file is in git — commit and push it, never edit it on the server.
