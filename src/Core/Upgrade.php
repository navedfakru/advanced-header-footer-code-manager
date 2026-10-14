<?php
/**
 * Activation, deactivation, upgrades and multisite setup.
 *
 * Deactivation never deletes data. Permanent deletion only happens in
 * uninstall.php and only when "Delete data on uninstall" is enabled.
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle handler.
 */
final class Upgrade {

	const VERSION_OPTION = 'ahfcm_version';

	/**
	 * Registers runtime hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'wp_initialize_site', array( __CLASS__, 'on_new_site' ), 20 );
	}

	/**
	 * Activation hook.
	 *
	 * @param bool $network_wide Whether activated network-wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}
		self::install();
	}

	/**
	 * Deactivation hook. Keeps all data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Intentionally keeps all data.
	}

	/**
	 * Sets up a site created after network activation.
	 *
	 * @param \WP_Site $site New site.
	 * @return void
	 */
	public static function on_new_site( $site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active_for_network( plugin_basename( AHFCM_FILE ) ) ) {
			switch_to_blog( $site->blog_id );
			self::install();
			restore_current_blog();
		}
	}

	/**
	 * Creates default options without touching existing data.
	 *
	 * @return void
	 */
	public static function install() {
		// add_option() is a no-op when the option exists, so saved code survives reactivation.
		add_option( Settings::OPTION, Settings::defaults(), '', true );
		self::maybe_upgrade();
	}

	/**
	 * Rewrites per-post code saved as one code+language into HTML/CSS/JS
	 * parts. Reading converts old data anyway; this keeps storage uniform.
	 * Revisions are left as they are (they convert when restored).
	 *
	 * @return void
	 */
	public static function convert_post_meta() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off upgrade; no API lists posts by meta key across all types.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT m.post_id FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_type <> 'revision'",
				PostMeta::META_KEY
			)
		);

		foreach ( array_map( 'intval', $ids ) as $post_id ) {
			$raw = get_post_meta( $post_id, PostMeta::META_KEY, true );
			if ( is_array( $raw ) ) {
				update_post_meta( $post_id, PostMeta::META_KEY, wp_slash( PostMeta::normalize( $raw ) ) );
			}
		}
	}

	/**
	 * Runs schema upgrades when the stored version is older than the code.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION, '0' );
		$current   = get_option( Settings::OPTION, null );
		$schema    = is_array( $current ) && isset( $current['version'] ) ? (int) $current['version'] : 0;

		if ( version_compare( $installed, AHFCM_VERSION, '>=' ) && $schema >= Settings::SCHEMA_VERSION ) {
			return;
		}

		if ( is_array( $current ) ) {
			// Schema 2: old default priorities (head 10, footer 100) printed code
			// before Breakdance's CSS/JS. Move them to the new default; custom
			// values are left alone.
			if ( $schema < 2 ) {
				$old = array(
					'head'   => 10,
					'footer' => 100,
				);
				foreach ( $old as $slot => $priority ) {
					if ( ! isset( $current['slots'][ $slot ]['priority'] ) || (int) $current['slots'][ $slot ]['priority'] === $priority ) {
						$current['slots'][ $slot ]['priority'] = Settings::DEFAULT_PRIORITY;
					}
				}
			}

			// Re-normalizing fills keys added by newer schemas with defaults
			// while keeping every existing value. Schema 3 (separate HTML/CSS/JS
			// parts) is converted from the old code+language shape here.
			$current['version'] = Settings::SCHEMA_VERSION;
			update_option( Settings::OPTION, Settings::normalize( $current ) );
		}

		if ( $schema < 3 ) {
			self::convert_post_meta();
		}

		update_option( self::VERSION_OPTION, AHFCM_VERSION, false );
	}
}
