<?php
/**
 * Who sees the toolbar buttons (block editor and page builders).
 *
 * Visibility only: hiding a button never removes code access; that is
 * governed by Security's capabilities.
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Toolbar button visibility rules.
 */
final class Visibility {

	const WHERE_BUILDER = 'builder';
	const WHERE_EDITOR  = 'editor';

	/**
	 * Roles that could ever see a button: they can publish raw HTML and, unless
	 * editors may edit page code, manage options.
	 *
	 * @return array<string,string> Role slug => name.
	 */
	public static function eligible_roles() {
		$editors = Settings::get()['editors_page_code'];
		$roles   = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			$caps = $role['capabilities'];
			if ( ! empty( $caps['unfiltered_html'] ) && ( $editors || ! empty( $caps['manage_options'] ) ) ) {
				$roles[ $slug ] = translate_user_role( $role['name'] );
			}
		}
		return $roles;
	}

	/**
	 * Users who could ever see a button.
	 *
	 * @return \WP_User[]
	 */
	public static function eligible_users() {
		$roles = array_keys( self::eligible_roles() );
		if ( ! $roles ) {
			return array();
		}
		return get_users(
			array(
				'role__in' => $roles,
				'orderby'  => 'display_name',
				'number'   => 200,
			)
		);
	}

	/**
	 * Whether a toolbar button is shown to a user.
	 *
	 * @param \WP_User|null $user  User (default current).
	 * @param string        $where builder (Breakdance/Oxygen) or editor (block editor).
	 * @return bool
	 */
	public static function button_visible_for( $user = null, $where = self::WHERE_BUILDER ) {
		$user     = $user ? $user : wp_get_current_user();
		$settings = Settings::get();
		$switch   = self::WHERE_EDITOR === $where ? 'editor_button' : 'builder_button';
		$visible  = true;

		if ( ! $settings[ $switch ] || ! $user || ! $user->exists() ) {
			$visible = false;
		} elseif ( in_array( (int) $user->ID, $settings['builder_button_hide_users'], true ) ) {
			$visible = false;
		} elseif ( null !== $settings['builder_button_roles'] && ! array_intersect( (array) $user->roles, $settings['builder_button_roles'] ) ) {
			// Super admins on multisite have no role on the site; let them through.
			$visible = is_multisite() && is_super_admin( $user->ID );
		}

		/**
		 * Filters whether a Header & Footer Code toolbar button is shown.
		 *
		 * @param bool     $visible Visible.
		 * @param \WP_User $user    User.
		 * @param string   $where   builder|editor.
		 */
		return (bool) apply_filters( 'ahfcm_breakdance_button_visible', $visible, $user, $where );
	}
}
