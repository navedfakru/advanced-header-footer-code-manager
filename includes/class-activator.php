<?php
/**
 * Activation, deactivation, upgrades and multisite setup.
 *
 * Deactivation never deletes data. Permanent deletion only happens in
 * uninstall.php and only when "Delete data on uninstall" is enabled.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle handler.
 */
final class Activator {

	const VERSION_OPTION = 'ahfcm_version';

	/**
	 * Registers runtime hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
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
	 * Runs schema upgrades when the stored version is older than the code.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $installed, AHFCM_VERSION, '>=' ) ) {
			return;
		}

		// Re-normalizing fills keys added by newer schema versions with defaults
		// while keeping every existing value. Future breaking changes go here,
		// keyed on the stored `version` field.
		$current = get_option( Settings::OPTION, null );
		if ( is_array( $current ) ) {
			update_option( Settings::OPTION, Settings::normalize( $current ) );
		}

		update_option( self::VERSION_OPTION, AHFCM_VERSION, false );
	}
}
