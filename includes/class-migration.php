<?php
/**
 * Optional import from "Page Header & Body Code Embed" v3.0
 * (WPCodeBox 2 snippet #7).
 *
 * Legacy storage (per post, raw strings):
 *
 *     _page_header_code  printed in wp_head (priority 999)
 *     _page_body_code    printed in wp_footer (priority 10) — despite the
 *                        "after <body>" label in its meta box
 *
 * The import:
 * - never modifies or deletes legacy meta;
 * - maps _page_body_code to the FOOTER slot, which is where it really ran;
 * - only fills empty slots, so re-running it never duplicates code;
 * - imports slots DISABLED unless "enable" is chosen, so front-end output
 *   does not change while the legacy snippet is still active.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy importer.
 */
final class Migration {

	const LEGACY_HEAD = '_page_header_code';
	const LEGACY_BODY = '_page_body_code';

	/**
	 * Whether the legacy implementation is loaded in this request.
	 *
	 * @return bool
	 */
	public static function legacy_active() {
		return function_exists( 'page_code_embed_callback' );
	}

	/**
	 * Posts with non-empty legacy code.
	 *
	 * @return int[]
	 */
	public static function find_legacy_posts() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off admin tool; no API returns posts by non-empty meta across all types and statuses.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE m.meta_key IN (%s, %s) AND m.meta_value <> '' AND p.post_type <> 'revision'
				ORDER BY m.post_id ASC",
				self::LEGACY_HEAD,
				self::LEGACY_BODY
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Plans or performs the import.
	 *
	 * @param array $args {
	 *     @type bool   $dry_run     Only report (default true).
	 *     @type bool   $enable      Enable imported slots (default false).
	 * }
	 * @return array[] One row per legacy field: post_id, title, field, target, status, notes.
	 */
	public static function run( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'dry_run'     => true,
				'enable'      => false,
			)
		);

		$map = array(
			self::LEGACY_HEAD => 'head',
			self::LEGACY_BODY => 'footer',
		);

		$report = array();

		foreach ( self::find_legacy_posts() as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$data    = Post_Meta::get( $post_id );
			$changed = false;

			foreach ( $map as $legacy_key => $slot ) {
				$legacy = get_post_meta( $post_id, $legacy_key, true );
				if ( ! is_string( $legacy ) || '' === trim( $legacy ) ) {
					continue;
				}

				$legacy = Settings::normalize_code( $legacy );
				$row    = array(
					'post_id' => $post_id,
					'title'   => get_the_title( $post ),
					'field'   => $legacy_key,
					'target'  => $slot,
					'status'  => '',
					'notes'   => array(),
				);

				// The legacy plugin ran stripslashes() on output, so its rendered
				// output differed from what was stored whenever backslashes exist.
				if ( stripslashes( $legacy ) !== $legacy ) {
					$row['notes'][] = __( 'Contains backslashes. The old plugin removed them on output; this plugin outputs code exactly as stored. Review before enabling.', 'advanced-header-footer-code-manager' );
				}
				if ( ! in_array( $post->post_type, Post_Meta::supported_post_types(), true ) ) {
					$row['notes'][] = __( 'Post type is not enabled for per-post code; the imported code will not run until it is.', 'advanced-header-footer-code-manager' );
				}

				$current = $data['slots'][ $slot ]['code'];

				if ( $current === $legacy ) {
					if ( $args['enable'] && ! $data['slots'][ $slot ]['enabled'] ) {
						$row['status']                     = 'enable';
						$data['slots'][ $slot ]['enabled'] = true;
						$changed                           = true;
					} else {
						$row['status'] = 'already_imported';
					}
				} elseif ( '' !== trim( $current ) ) {
					$row['status'] = 'skipped_conflict';
				} else {
					$row['status']                      = 'import';
					$data['slots'][ $slot ]['code']     = $legacy;
					$data['slots'][ $slot ]['language'] = 'html';
					$data['slots'][ $slot ]['enabled']  = (bool) $args['enable'];
					$changed                            = true;
				}

				$report[] = $row;
			}

			if ( $changed && ! $args['dry_run'] ) {
				Post_Meta::save( $post_id, $data );

				$saved = Post_Meta::get( $post_id );
				foreach ( $report as &$row ) {
					if ( $row['post_id'] !== $post_id || ! in_array( $row['status'], array( 'import', 'enable' ), true ) ) {
						continue;
					}
					$ok            = Settings::normalize_code( (string) get_post_meta( $post_id, $row['field'], true ) ) === $saved['slots'][ $row['target'] ]['code'];
					$row['status'] = $ok ? ( 'import' === $row['status'] ? 'imported' : 'enabled' ) : 'failed';
				}
				unset( $row );
			}
		}

		return $report;
	}

	/**
	 * Human label for a report status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'import'           => __( 'Will be imported', 'advanced-header-footer-code-manager' ),
			'enable'           => __( 'Will be enabled', 'advanced-header-footer-code-manager' ),
			'imported'         => __( 'Imported', 'advanced-header-footer-code-manager' ),
			'enabled'          => __( 'Enabled', 'advanced-header-footer-code-manager' ),
			'already_imported' => __( 'Already imported', 'advanced-header-footer-code-manager' ),
			'skipped_conflict' => __( 'Skipped: slot already has different code', 'advanced-header-footer-code-manager' ),
			'failed'           => __( 'Failed: verification mismatch', 'advanced-header-footer-code-manager' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
