<?php
/**
 * MyIGNITE Event Importer - admin settings screen.
 *
 * Loaded only inside wp-admin (see the conditional require in the main plugin
 * file), so nothing here is ever parsed on a front-end request. A bug on this
 * screen therefore cannot take down the public site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MYIGNITE_ADMIN_SLUG', 'myignite-event-importer' );

add_action( 'admin_menu', 'myignite_admin_menu' );
function myignite_admin_menu() {
	add_submenu_page(
		'edit.php?post_type=tribe_events',
		'MyIGNITE Importer',
		'MyIGNITE Importer',
		'manage_options',
		MYIGNITE_ADMIN_SLUG,
		'myignite_render_settings_page'
	);
}

function myignite_admin_url() {
	return admin_url( 'edit.php?post_type=tribe_events&page=' . MYIGNITE_ADMIN_SLUG );
}

add_filter( 'plugin_action_links_myignite-event-importer/myignite-event-importer.php', 'myignite_plugin_action_links' );
function myignite_plugin_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( myignite_admin_url() ) . '">Settings</a>' );
	return $links;
}

function myignite_admin_styles() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	echo '<style>
		.myig-wrap { max-width: 860px; }
		.myig-card { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:16px 20px; margin:0 0 20px; }
		.myig-card h2 { margin-top:0; font-size:15px; }
		.myig-help { color:#646970; font-size:13px; margin:4px 0 14px; }
		.myig-groups { margin:0; }
		.myig-groups li { margin:0 0 6px; }
		.myig-gid { color:#8c8f94; font-size:12px; }
		.myig-lbl { display:inline-block; min-width:92px; font-weight:600; }
		.myig-fields p { margin:0 0 10px; }
		.myig-note { background:#f0f6fc; border-left:4px solid #72aee6; padding:10px 14px; margin:0 0 16px; font-size:13px; }
		.myig-note code { background:rgba(0,0,0,.05); padding:1px 5px; }
		.myig-error { background:#fdecea; border-left:4px solid #dd9999; color:#7a2e28; padding:10px 14px; margin:14px 0; font-size:13px; }
		.myig-error code { background:rgba(0,0,0,.06); padding:1px 5px; }
		.myig-discovered { margin-top:12px; border-top:1px solid #e0e0e0; padding-top:12px; }
		.myig-status { font-size:13px; color:#646970; }
	</style>';
}

function myignite_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to access this page.' );
	}

	myignite_admin_styles();
	myignite_handle_post();

	$known      = myignite_importer_known_groups();
	$enabled    = array_keys( myignite_importer_enabled_groups() );
	$next_cron  = wp_next_scheduled( MYIGNITE_IMPORTER_CRON_HOOK );
	$discovered = get_transient( 'myignite_discovered_groups' );
	$today      = myignite_importer_toronto_format( time(), 'Y-m-d' );

	echo '<div class="wrap myig-wrap"><h1>MyIGNITE Event Importer</h1>';

	myignite_render_error_notice();

	echo '<p class="myig-status">Events are imported automatically <strong>once a day at 6:00 PM Toronto time</strong>. ';
	if ( $next_cron ) {
		echo 'Next run: <strong>' . esc_html( myignite_importer_toronto_format( $next_cron ) ) . '</strong>. ';
	} else {
		echo '<strong>Not currently scheduled</strong> &mdash; deactivating and reactivating the plugin restores it. ';
	}
	echo 'Every run is logged to <code>wp-content/myignite-event-sync.log</code>.</p>';

	echo '<form method="post">';
	wp_nonce_field( 'myignite_save', 'myignite_nonce' );

	// ---------------------------------------------------------------- groups
	echo '<div class="myig-card"><h2>Groups to import from</h2>';
	echo '<p class="myig-help"><strong>How to use:</strong> tick the CampusGroups groups whose events '
		. 'should appear on the website, then click <em>Save settings</em>. Unticking a group stops its '
		. 'events being imported or updated from the next run onward; events already on the website are '
		. 'left in place, not deleted.</p>';

	echo '<ul class="myig-groups">';
	foreach ( $known as $gid => $gname ) {
		printf(
			'<li><label><input type="checkbox" name="myig_groups[]" value="%d" %s /> %s <span class="myig-gid">(ID %d)</span></label></li>',
			(int) $gid,
			checked( in_array( (int) $gid, $enabled, true ), true, false ),
			esc_html( $gname ),
			(int) $gid
		);
	}
	echo '</ul>';

	echo '<p style="margin-top:14px;"><button type="submit" name="myig_action" value="discover" class="button">Check for other groups</button> '
		. '<span class="myig-help" style="display:inline;">Looks for groups not already listed above, among those with '
		. 'at least one recent or upcoming event right now &mdash; a group with none won\'t show up here until it has '
		. 'one. Only runs when clicked, to keep API calls down.</span></p>';

	if ( is_array( $discovered ) ) {
		echo '<div class="myig-discovered">';
		if ( ! $discovered ) {
			echo '<p class="myig-help">No additional groups found on CampusGroups.</p>';
		} else {
			echo '<p class="myig-help">Found ' . (int) count( $discovered ) . ' other group(s). Tick any you want to '
				. 'import from and click <em>Save settings</em> &mdash; they then join the list above permanently.</p><ul class="myig-groups">';
			foreach ( $discovered as $gid => $gname ) {
				printf(
					'<li><label><input type="checkbox" name="myig_add[%d]" value="%s" /> %s <span class="myig-gid">(ID %d)</span></label></li>',
					(int) $gid,
					esc_attr( $gname ),
					esc_html( $gname ),
					(int) $gid
				);
			}
			echo '</ul>';
		}
		echo '</div>';
	}
	echo '</div>';

	// ------------------------------------------------------------ manual run
	echo '<div class="myig-card"><h2>Run an import now</h2>';
	echo '<p class="myig-help"><strong>How to use:</strong> pick the range of <em>event dates</em> you want '
		. 'to import, then click <em>Run import now</em>. Leaving the defaults imports every upcoming event '
		. 'from today onward &mdash; the same thing the daily 6:00 PM run does. All times are '
		. '<strong>Toronto time</strong>, and daylight saving is applied automatically, so there is nothing '
		. 'to convert.</p>';

	echo '<div class="myig-fields">';
	printf(
		'<p><label class="myig-lbl" for="myig_start_date">Start date</label>'
		. '<input type="date" id="myig_start_date" name="myig_start_date" value="%s" /> '
		. '<input type="time" name="myig_start_time" value="00:00" /></p>',
		esc_attr( $today )
	);
	echo '<p><label class="myig-lbl" for="myig_end_date">End date</label>'
		. '<input type="date" id="myig_end_date" name="myig_end_date" value="" /> '
		. '<input type="time" name="myig_end_time" value="23:59" /> '
		. '<span class="myig-help" style="display:inline;">Leave blank for all upcoming events.</span></p>';
	echo '<p><label><input type="checkbox" name="myig_inclusive" value="1" checked /> Both dates inclusive</label></p>';
	echo '</div>';

	echo '<p><button type="submit" name="myig_action" value="run" class="button button-primary">Run import now</button> '
		. '<button type="submit" name="myig_action" value="dryrun" class="button">Preview (dry run)</button> '
		. '<span class="myig-help" style="display:inline;">Preview reports what would change without writing anything.</span></p>';
	echo '</div>';

	// ------------------------------------------------------------ API keys
	echo '<div class="myig-card"><h2>CampusGroups API credentials</h2>';
	echo '<div class="myig-note">The API secret and school code are <strong>not stored on this screen</strong>. '
		. 'They are PHP constants kept outside the plugin, so they are never shown in the admin UI or committed '
		. 'to version control. To change them, edit:<br /><br />'
		. '<code>wp-content/mu-plugins/myignite-secrets.php</code><br /><br />'
		. 'which defines <code>MYIGNITE_CG_SCHOOL_CODE</code> and <code>MYIGNITE_CG_API_SECRET</code>. '
		. 'If CampusGroups rotates the secret, paste the new value into that file over SFTP/SSH. Nothing needs '
		. 'changing here and no redeploy is required.</div>';
	echo '<p class="myig-status">Current status: ';
	if ( myignite_importer_cg_credentials() ) {
		echo '<strong style="color:#1a7f37;">credentials are configured</strong> &mdash; note this only confirms '
			. 'the constants exist, not that CampusGroups still accepts them.';
	} else {
		echo '<strong style="color:#a94442;">not configured</strong> &mdash; imports cannot run.';
	}
	echo '</p></div>';

	echo '<p><button type="submit" name="myig_action" value="save" class="button button-primary">Save settings</button></p>';
	echo '</form></div>';
}

/**
 * Handles every submit button. Nonce and capability are checked once, here,
 * so no individual action can bypass them.
 */
function myignite_handle_post() {
	if ( empty( $_POST['myig_action'] ) ) {
		return;
	}
	if ( ! isset( $_POST['myignite_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['myignite_nonce'] ) ), 'myignite_save' ) ) {
		echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = sanitize_text_field( wp_unslash( $_POST['myig_action'] ) );

	if ( 'discover' === $action ) {
		myignite_handle_discover();
		return;
	}

	// Saving first means a run always uses exactly what is on screen.
	myignite_save_groups();

	if ( 'run' === $action || 'dryrun' === $action ) {
		myignite_handle_manual_run( 'dryrun' === $action );
	} else {
		echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
	}
}

/**
 * Persists the group list plus which entries are ticked.
 */
function myignite_save_groups() {
	$known = myignite_importer_known_groups();
	$added = array();

	if ( ! empty( $_POST['myig_add'] ) && is_array( $_POST['myig_add'] ) ) {
		foreach ( wp_unslash( $_POST['myig_add'] ) as $gid => $gname ) {
			$gid = (int) $gid;
			if ( $gid > 0 ) {
				$known[ $gid ] = sanitize_text_field( $gname );
				$added[]       = $gid;
			}
		}
	}

	$enabled = array();
	if ( ! empty( $_POST['myig_groups'] ) && is_array( $_POST['myig_groups'] ) ) {
		foreach ( wp_unslash( $_POST['myig_groups'] ) as $gid ) {
			$gid = (int) $gid;
			if ( isset( $known[ $gid ] ) ) {
				$enabled[] = $gid;
			}
		}
	}

	// A newly added group is ticked by default - ticking it is how it was added.
	foreach ( $added as $gid ) {
		if ( ! in_array( $gid, $enabled, true ) ) {
			$enabled[] = $gid;
		}
	}

	update_option( MYIGNITE_OPT_GROUPS, $known, false );
	update_option( MYIGNITE_OPT_ENABLED, array_values( array_unique( $enabled ) ), false );

	delete_transient( 'myignite_discovered_groups' );

	if ( ! $enabled ) {
		echo '<div class="notice notice-warning is-dismissible"><p><strong>No groups are ticked.</strong> '
			. 'No events will be imported until at least one group is selected.</p></div>';
	}
}

/**
 * Looks for CampusGroups groups not already in the list. Cached briefly so
 * repeated clicks do not repeatedly hit their API.
 *
 * The Data API has no standalone "list all groups" resource (unlike the old
 * Data Export API's /groups endpoint), so this derives the list from groups
 * that own at least one event currently returned by the events feed instead.
 * Known, accepted tradeoff: a group with zero recent/upcoming events won't
 * appear here until it has one.
 */
function myignite_handle_discover() {
	$events = myignite_importer_data_api_fetch_events();

	if ( is_wp_error( $events ) ) {
		myignite_importer_record_error( 'groups_fetch', $events->get_error_message() );
		echo '<div class="notice notice-error is-dismissible"><p>Could not reach CampusGroups: '
			. esc_html( $events->get_error_message() ) . '</p></div>';
		return;
	}

	$known = myignite_importer_known_groups();
	$found = array();

	foreach ( $events as $event ) {
		$gid = (int) ( $event['groupId'] ?? 0 );
		if ( ! $gid || isset( $known[ $gid ] ) || isset( $found[ $gid ] ) ) {
			continue;
		}
		$gname = trim( (string) ( $event['group'] ?? '' ) );
		if ( '' === $gname ) {
			continue;
		}
		$found[ $gid ] = $gname;
	}

	asort( $found );
	set_transient( 'myignite_discovered_groups', $found, 15 * MINUTE_IN_SECONDS );

	echo '<div class="notice notice-success is-dismissible"><p>Checked CampusGroups &mdash; found '
		. (int) count( $found ) . ' group(s) not already in your list.</p></div>';
}

/**
 * Runs the importer for the chosen event-date range.
 *
 * Deliberately calls the same myignite_sync_events() that WP-Cron and WP-CLI
 * call - this only translates the form's Toronto-local fields into the
 * options that function already understands.
 */
function myignite_handle_manual_run( $dry_run ) {
	$inclusive = ! empty( $_POST['myig_inclusive'] );

	$start_ts = myignite_importer_toronto_to_timestamp(
		isset( $_POST['myig_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['myig_start_date'] ) ) : '',
		isset( $_POST['myig_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['myig_start_time'] ) ) : '00:00'
	);
	$end_ts = myignite_importer_toronto_to_timestamp(
		isset( $_POST['myig_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['myig_end_date'] ) ) : '',
		isset( $_POST['myig_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['myig_end_time'] ) ) : '23:59'
	);

	// Blank start means today; blank end means unbounded (all upcoming).
	if ( null === $start_ts ) {
		$start_ts = myignite_importer_toronto_to_timestamp( myignite_importer_toronto_format( time(), 'Y-m-d' ), '00:00' );
	}

	if ( $end_ts && $end_ts < $start_ts ) {
		echo '<div class="notice notice-error is-dismissible"><p>The end date is before the start date.</p></div>';
		return;
	}

	$result = myignite_sync_events( array(
		'dry_run'         => $dry_run,
		'event_start_min' => $start_ts,
		'event_start_max' => $end_ts,
		'inclusive'       => $inclusive,
	) );

	$range = myignite_importer_toronto_format( $start_ts )
		. ' to ' . ( $end_ts ? myignite_importer_toronto_format( $end_ts ) : 'all upcoming' );

	if ( is_array( $result ) ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%s complete.</strong> %s<br />'
			. 'Created: %d &middot; Updated: %d &middot; Skipped: %d &middot; Trashed: %d &middot; Errors: %d</p></div>',
			$dry_run ? 'Dry run' : 'Import',
			esc_html( $range ),
			(int) $result['created'],
			(int) $result['updated'],
			(int) $result['skipped'],
			(int) $result['trashed'],
			(int) $result['error']
		);
	} else {
		echo '<div class="notice notice-error is-dismissible"><p>The import could not complete. See the message '
			. 'above, or <code>wp-content/myignite-event-sync.log</code>.</p></div>';
	}
}

/**
 * Shows the most recent import failure on the Events screens, in a muted red
 * so it reads as a warning rather than an alarm. Dismissing hides it for that
 * user until a newer failure occurs; a successful run clears it for everyone.
 */
add_action( 'admin_notices', 'myignite_render_error_notice_global' );
function myignite_render_error_notice_global() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}
	// Our own settings screen renders it inline, higher up the page.
	if ( false !== strpos( (string) $screen->id, MYIGNITE_ADMIN_SLUG ) ) {
		return;
	}
	if ( 'tribe_events' !== $screen->post_type ) {
		return;
	}
	myignite_admin_styles();
	myignite_render_error_notice();
}

function myignite_render_error_notice() {
	$err = get_option( MYIGNITE_OPT_LAST_ERROR );
	if ( ! is_array( $err ) || empty( $err['message'] ) ) {
		return;
	}

	$dismissed = (int) get_user_meta( get_current_user_id(), 'myignite_dismissed_error', true );
	if ( $dismissed && $dismissed >= (int) ( $err['time'] ?? 0 ) ) {
		return;
	}

	$msg = (string) $err['message'];

	// Point at the likeliest cause when it looks like an auth rejection.
	$hint = '';
	if ( 'auth' === ( $err['code'] ?? '' )
		|| false !== stripos( $msg, '401' )
		|| false !== stripos( $msg, '403' ) ) {
		$hint = '<br /><br />This usually means the CampusGroups API secret was rotated. Update '
			. '<code>MYIGNITE_CG_API_SECRET</code> in <code>wp-content/mu-plugins/myignite-secrets.php</code>.';
	}

	printf(
		'<div class="myig-error"><strong>MyIGNITE import problem</strong> (%s)<br />%s%s<br /><br /><a href="%s">Dismiss</a></div>',
		esc_html( myignite_importer_toronto_format( (int) ( $err['time'] ?? time() ) ) ),
		esc_html( $msg ),
		$hint,
		esc_url( wp_nonce_url( add_query_arg( 'myignite_dismiss', '1' ), 'myignite_dismiss' ) )
	);
}

add_action( 'admin_init', 'myignite_handle_dismiss' );
function myignite_handle_dismiss() {
	if ( empty( $_GET['myignite_dismiss'] ) ) {
		return;
	}
	if ( ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'myignite_dismiss' ) ) {
		return;
	}
	update_user_meta( get_current_user_id(), 'myignite_dismissed_error', time() );
	wp_safe_redirect( remove_query_arg( array( 'myignite_dismiss', '_wpnonce' ) ) );
	exit;
}
