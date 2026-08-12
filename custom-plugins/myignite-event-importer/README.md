# MyIGNITE Event Importer — operations notes

Imports events from MyIGNITE (CampusGroups) into The Events Calendar.
Replaced the Event Aggregator ICS pipeline on 2026-08-08.

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
updated from the next run onward — events already on the website are left in
place, not deleted.

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

Leaving both dates at their defaults does exactly what the daily run does.

## What runs, and when

| What | When |
|---|---|
| `myignite_event_sync_event` (WP-Cron) | Once daily, **6:00 PM Toronto** |
| `wp myignite sync-events` | On demand (add `--dry-run` to preview) |
| **Run import now** button | On demand, from the settings screen |

Log: `wp-content/myignite-event-sync.log` (blocked from public HTTP access).

**Upcoming events only.** Every run imports events starting **today or later**.
Past events are never created or modified, and — importantly — an event that
merely ages into the past is *not* trashed; it is filtered out before any
create/update/trash decision is made, so it simply stays as it is.

**Freshness caveat:** CampusGroups' Data Export API is fed by a batch job on
their side, measured at roughly 18–30 hours behind live. An event created in
CampusGroups today generally will not appear here until tomorrow, no matter how
often we poll — which is why the schedule is once a day rather than hourly.

To publish something sooner, create the event by hand in wp-admin using the
**exact same title and start time** as the CampusGroups event. The next sync
adopts that post instead of creating a duplicate, and CampusGroups becomes the
source of truth for it from then on.

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

- **Events** — `https://<school>.service.campusgroups.com/data/v1/events`
  (async: POST returns a `queryId`, GET polls until 200). Auth via
  `X-CG-API-Secret` + `X-CG-School`.
- **Groups** — the same API's `/groups` resource, used by *Check for other
  groups*.
- **Images** — `mobile_ws/v17/mobile_events_list`, the undocumented endpoint
  behind the public events page. The Data Export API has no image field at all.
  It returns *all* upcoming public events (`range` is an offset, not a page
  limit), so it does not cap at any particular number.

### Image resolution

`mobile_ws` reports the `r2_` variant, which is only 640×320 and looks blurry
once the theme renders it (`card-tribe_events.php` uses
`the_post_thumbnail('full')` inside a 4:3 `object-cover` box).
`myignite_importer_image_url_candidates()` therefore tries, in order: the
unprefixed original (often 2048px), then `r3_` (1280×640), then the `r2_` URL as
given. **Do not "simplify" this back to using the API's URL directly.**

## Which events get published

An event is imported only when **all** of these are true on CampusGroups:

| Requirement | Field |
|---|---|
| Hosted by a ticked group | `groupId` |
| Approved | `approvalStatus = Approved` |
| Not a draft | `draft = false` |
| Not deleted | `deleted = false` |
| **Publicly visible** | `whoCanSeeEventOnCalendar = Everyone` |
| Starts today or later | `startDate` |

The visibility rule matters most often in practice. An event set to
**See Event on Calendar → No one** is hidden from CampusGroups' own public
events list, so it is not something to publish on the public website — and,
because that same list is our only image source, it could never have a featured
image anyway.

If an event should appear on the website but does not, check that setting first;
it is the usual cause. Changing it on CampusGroups is enough — the next run
picks the event up, image included, with no code change.

An event that already exists on the website and later stops meeting any of these
rules is moved to **Trash** (not deleted), and the log records which specific
rule removed it.

> Failing open by design: if `whoCanSeeEventOnCalendar` is missing from the API
> response entirely — e.g. CampusGroups renames the field — events are treated
> as visible rather than hidden. Failing closed would let one upstream schema
> change trash the entire calendar in a single run.

An event with no image usually means one of:

- its **See Event on Calendar** setting is *No one* on CampusGroups, so it is
  absent from the public list `mobile_ws` serves — the fix is on CampusGroups,
  not here; or
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
- The API window is on `updatedOn`, **not** the event date. An earlier version
  used an incremental "changed since last run" window; that silently did
  nothing when the change was on *our* side (e.g. images deleted locally), so
  every run now queries a wide window and filters by event date instead.
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
