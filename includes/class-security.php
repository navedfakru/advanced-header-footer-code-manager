<?php
/**
 * Capability model.
 *
 * Two meta capabilities are mapped onto core capabilities:
 *
 * - ahfcm_manage_global          => manage_options + unfiltered_html
 * - ahfcm_edit_post_code (post)  => edit_post( $post_id ) + unfiltered_html
 *
 * Requiring unfiltered_html means only users who could already publish raw
 * <script> tags through core can use this plugin. On multisite that is super
 * admins only, and DISALLOW_UNFILTERED_HTML disables the plugin's editors.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Security helpers.
 */
final class Security {

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
		if ( self::CAP_GLOBAL === $cap ) {
			$caps = array_merge(
				map_meta_cap( 'manage_options', $user_id ),
				map_meta_cap( 'unfiltered_html', $user_id )
			);
		} elseif ( self::CAP_POST === $cap ) {
			$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
			if ( ! $post_id || ! get_post( $post_id ) ) {
				return array( 'do_not_allow' );
			}
			$caps = array_merge(
				map_meta_cap( 'edit_post', $user_id, $post_id ),
				map_meta_cap( 'unfiltered_html', $user_id )
			);
		}

		return array_values( array_unique( $caps ) );
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
