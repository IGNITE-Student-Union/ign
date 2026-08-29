<?php
/**
 * Plugin Name: IGNITE - Drop Original Images
 * Description: One-time WP-CLI cleanup that deletes the full-size original WordPress
 *              preserves after scaling an oversized upload. Exposes the
 *              `wp ignite drop-originals` command and nothing else - there is no
 *              upload hook and no automatic behavior.
 * Version:     2.0.0
 * Author:      IGNITE Student Union
 *
 * This plugin does NOT change WordPress's big_image_size_threshold (left at the
 * core default of 2560) and does NOT delete anything on upload. It acts only
 * when the WP-CLI command below is run by hand.
 *
 *   wp ignite drop-originals --dry-run   # report only, deletes nothing
 *   wp ignite drop-originals             # permanent deletion: no trash, no undo
 *
 * WARNING: deletion is immediate and permanent. Confirm a current backup exists
 * before running without --dry-run.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the absolute path of the preserved original from attachment metadata.
 *
 * @param array $metadata Attachment metadata.
 * @return string|false Absolute path, or false when there is no distinct original.
 */
function ignite_locate_original( $metadata ) {
	if ( empty( $metadata['original_image'] ) || empty( $metadata['file'] ) ) {
		return false;
	}

	$uploads = wp_get_upload_dir();

	if ( ! empty( $uploads['error'] ) ) {
		return false;
	}

	$subdir = dirname( $metadata['file'] );
	$subdir = ( '.' === $subdir ) ? '' : $subdir;

	$original = path_join( $uploads['basedir'], path_join( $subdir, $metadata['original_image'] ) );
	$scaled   = path_join( $uploads['basedir'], $metadata['file'] );

	// Never return the file the site is actually serving.
	return ( $original === $scaled ) ? false : $original;
}

/**
 * One-time cleanup for media that still has a preserved full-size original.
 *
 *   wp ignite drop-originals --dry-run
 *   wp ignite drop-originals
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'ignite drop-originals',
		static function ( $args, $assoc_args ) {
			$dry_run  = isset( $assoc_args['dry-run'] );
			$per_page = 200;
			$paged    = 1;

			$found   = 0;
			$deleted = 0;
			$bytes   = 0;

			do {
				$query = new WP_Query(
					array(
						'post_type'              => 'attachment',
						'post_mime_type'         => 'image',
						'post_status'            => 'inherit',
						'posts_per_page'         => $per_page,
						'paged'                  => $paged,
						'fields'                 => 'ids',
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
					)
				);

				foreach ( $query->posts as $id ) {
					$metadata = wp_get_attachment_metadata( $id );

					if ( ! is_array( $metadata ) || empty( $metadata['original_image'] ) ) {
						continue;
					}

					$original = ignite_locate_original( $metadata );

					if ( ! $original || ! file_exists( $original ) ) {
						continue;
					}

					++$found;
					$size   = (int) filesize( $original );
					$bytes += $size;

					WP_CLI::log(
						sprintf(
							'%s #%d  %s  (%s)',
							$dry_run ? '[dry-run]' : '[delete] ',
							$id,
							$metadata['original_image'],
							size_format( $size, 1 )
						)
					);

					if ( $dry_run ) {
						continue;
					}

					wp_delete_file( $original );
					unset( $metadata['original_image'] );
					wp_update_attachment_metadata( $id, $metadata );
					++$deleted;
				}

				$batch = count( $query->posts );
				++$paged;

				// Release the per-batch object cache so memory stays flat on
				// large media libraries.
				if ( is_callable( 'WP_CLI\Utils\wp_clear_object_cache' ) ) {
					call_user_func( 'WP_CLI\Utils\wp_clear_object_cache' );
				}
			} while ( $batch === $per_page );

			WP_CLI::success(
				sprintf(
					'%d originals found, %d deleted, %s %s.',
					$found,
					$deleted,
					size_format( $bytes, 1 ),
					$dry_run ? 'recoverable' : 'reclaimed'
				)
			);
		}
	);
}
