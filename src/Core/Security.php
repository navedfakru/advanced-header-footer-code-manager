<?php
/**
 * Capability model.
 *
 * Three meta capabilities are mapped onto core capabilities:
 *
 * - ahfcm_view                   => manage_options (settings page, change log, safe mode)
 * - ahfcm_manage_global          => manage_options + unfiltered_html
 * - ahfcm_edit_post_code (post)  => edit_post( $post_id ) + unfiltered_html
 *                                   + manage_options, unless "Editors can edit
 *                                   page code" is on
 *
 * Requiring unfiltered_html means only users who could already publish raw
 * <script> tags through core can use this plugin. On multisite that is super
 * admins only, and DISALLOW_UNFILTERED_HTML disables the plugin's editors.
 *
 * Code editing is also locked, for everyone, when the site sets
 * DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS (the constants managed hosts use to
 * stop code changes from wp-admin). Existing code keeps running; it can be
 * switched off with safe mode.
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Security helpers.
 */
final class Security {

	const CAP_VIEW   = 'ahfcm_view';
	const CAP_GLOBAL = 'ahfcm_manage_global';
	const CAP_POST   = 'ahfcm_edit_post_code';

	/**
	 * Hooks the capability mapping.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Maps plugin meta capabilities to primitive capabilities.
	 *
	 * @param string[] $caps    Primitive capabilities required.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Extra arguments (post ID for CAP_POST).
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( self::CAP_VIEW === $cap ) {
			$caps = map_meta_cap( 'manage_options', $user_id );
		} elseif ( self::CAP_GLOBAL === $cap ) {
			if ( self::editing_locked() ) {
				return array( 'do_not_allow' );
			}
			$caps = array_merge(
				map_meta_cap( 'manage_options', $user_id ),
				map_meta_cap( 'unfiltered_html', $user_id )
			);
		} elseif ( self::CAP_POST === $cap ) {
			$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
			if ( self::editing_locked() || ! $post_id || ! get_post( $post_id ) ) {
				return array( 'do_not_allow' );
			}
			$caps = array_merge(
				map_meta_cap( 'edit_post', $user_id, $post_id ),
				map_meta_cap( 'unfiltered_html', $user_id )
			);
			if ( ! Settings::get()['editors_page_code'] ) {
				$caps = array_merge( $caps, map_meta_cap( 'manage_options', $user_id ) );
			}
		}

		return array_values( array_unique( $caps ) );
	}

	/**
	 * Why code editing is locked on this site, or '' when it is not.
	 *
	 * @return string DISALLOW_FILE_EDIT|DISALLOW_FILE_MODS|filter|''
	 */
	public static function lock_reason() {
		$reason = '';
		foreach ( array( 'DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS' ) as $constant ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				$reason = $constant;
				break;
			}
		}

		/**
		 * Filters whether code editing is locked for everyone. Existing code
		 * keeps running while locked.
		 *
		 * @param bool   $locked Locked.
		 * @param string $reason Constant that locks it, or ''.
		 */
		$locked = (bool) apply_filters( 'ahfcm_editing_locked', '' !== $reason, $reason );

		if ( $locked && '' === $reason ) {
			$reason = 'filter';
		}
		return $locked ? $reason : '';
	}

	/**
	 * Whether code editing is locked for everyone.
	 *
	 * @return bool
	 */
	public static function editing_locked() {
		return '' !== self::lock_reason();
	}

	/**
	 * Whether the current user may open the settings page (read-only when
	 * editing is locked).
	 *
	 * @return bool
	 */
	public static function can_view() {
		return current_user_can( self::CAP_VIEW );
	}

	/**
	 * Whether the current user may edit global code and plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage_global() {
		return current_user_can( self::CAP_GLOBAL );
	}

	/**
	 * Whether the current user may edit a post's code.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function can_edit_post_code( $post_id ) {
		return current_user_can( self::CAP_POST, (int) $post_id );
	}
}
