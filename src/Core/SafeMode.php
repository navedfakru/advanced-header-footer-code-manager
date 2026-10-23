<?php
/**
 * Safe mode: stop printing all code (global, builder documents and pages)
 * without editing or deleting any of it.
 *
 *     define( 'AHFCM_SAFE_MODE', true );   in wp-config.php, for everyone
 *     ?ahfcm_safe_mode=1                    on any URL, for that request only,
 *                                           when the visitor can manage options
 *
 * The URL form needs no nonce: it only removes output for the user who asked
 * for it and changes nothing that is stored. Use it to reach a page that a
 * broken snippet makes unusable, then fix or disable that snippet.
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Safe mode switches.
 */
final class SafeMode {

	const CONSTANT     = 'AHFCM_SAFE_MODE';
	const QUERY_ARG    = 'ahfcm_safe_mode';
	const ADMIN_BAR_ID = 'ahfcm-safe-mode';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 100 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	/**
	 * Whether safe mode is on through wp-config.php.
	 *
	 * @return bool
	 */
	public static function by_constant() {
		return defined( self::CONSTANT ) && constant( self::CONSTANT );
	}

	/**
	 * Whether the current request asked for safe mode in the URL, and may.
	 *
	 * Uses manage_options (not the code capabilities) so it also works on
	 * sites where code editing is locked.
	 *
	 * @return bool
	 */
	public static function by_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only switch, see file header.
		if ( empty( $_GET[ self::QUERY_ARG ] ) ) {
			return false;
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * Whether code output is suspended for this request.
	 *
	 * @return bool
	 */
	public static function active() {
		return self::by_constant() || self::by_request();
	}

	/**
	 * Front-end URL that opens the site in safe mode.
	 *
	 * @param string $url URL (default home).
	 * @return string
	 */
	public static function url( $url = '' ) {
		return add_query_arg( self::QUERY_ARG, '1', $url ? $url : home_url( '/' ) );
	}

	/**
	 * Shows "Safe mode" in the front-end admin bar while it is on.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function admin_bar( $bar ) {
		if ( is_admin() || ! self::active() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => self::ADMIN_BAR_ID,
				'title' => esc_html__( 'Header & Footer Code: safe mode', 'advanced-header-footer-code-manager' ),
				'href'  => self::by_constant() ? false : remove_query_arg( self::QUERY_ARG ),
				'meta'  => array(
					'title' => self::by_constant()
						? __( 'AHFCM_SAFE_MODE is set in wp-config.php: no header or footer code is printed.', 'advanced-header-footer-code-manager' )
						: __( 'No header or footer code is printed on this page. Click to leave safe mode.', 'advanced-header-footer-code-manager' ),
				),
			)
		);
	}

	/**
	 * Reminds administrators that the constant is switching output off.
	 *
	 * @return void
	 */
	public static function admin_notice() {
		if ( ! self::by_constant() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Header & Footer Code safe mode is on (AHFCM_SAFE_MODE in wp-config.php): no header or footer code is printed anywhere. Remove the constant to turn code back on.', 'advanced-header-footer-code-manager' ) . '</p></div>';
	}
}
