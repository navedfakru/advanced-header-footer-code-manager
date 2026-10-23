<?php
/**
 * Change log: who changed which code, and when.
 *
 * Option `ahfcm_change_log` (not autoloaded) keeps the newest LIMIT entries:
 *
 *     time       int    Unix time.
 *     user       int    User ID (0 = WP-CLI, cron or another plugin).
 *     user_name  string Display name at the time, kept if the user is deleted.
 *     action     string One of the ACTION_* constants.
 *     object     int    Post ID for page code, 0 for global code.
 *     details    string What changed, e.g. "Header: CSS, JS · Footer: turned off".
 *
 * Entries record what changed, never the code itself; earlier code lives in
 * the revisions (Settings::HISTORY_OPTION, PostMeta::HISTORY_META). There is
 * no UI to edit or clear the log.
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Change log storage.
 */
final class ChangeLog {

	const OPTION = 'ahfcm_change_log';
	const LIMIT  = 200;

	const ACTION_GLOBAL_CODE     = 'global_code';
	const ACTION_GLOBAL_SETTINGS = 'global_settings';
	const ACTION_GLOBAL_RESTORE  = 'global_restore';
	const ACTION_PAGE_CODE       = 'page_code';
	const ACTION_PAGE_RESTORE    = 'page_restore';
	const ACTION_PAGE_CLEARED    = 'page_cleared';

	/**
	 * Set right before a restore so the next recorded change is labelled as one.
	 *
	 * @var bool
	 */
	private static $restoring = false;

	/**
	 * Marks the next recorded change as a restore.
	 *
	 * @return void
	 */
	public static function mark_restore() {
		self::$restoring = true;
	}

	/**
	 * Returns and clears the restore flag.
	 *
	 * @return bool
	 */
	public static function take_restore() {
		$restoring       = self::$restoring;
		self::$restoring = false;
		return $restoring;
	}

	/**
	 * Adds an entry.
	 *
	 * @param string $action  ACTION_* constant.
	 * @param int    $object  Post ID or 0.
	 * @param string $details What changed.
	 * @return void
	 */
	public static function add( $action, $object = 0, $details = '' ) {
		$user = wp_get_current_user();

		$entries = self::get();
		array_unshift(
			$entries,
			array(
				'time'      => time(),
				'user'      => (int) $user->ID,
				'user_name' => $user->exists() ? $user->display_name : '',
				'action'    => (string) $action,
				'object'    => (int) $object,
				'details'   => (string) $details,
			)
		);
		update_option( self::OPTION, array_slice( $entries, 0, self::LIMIT ), false );
	}

	/**
	 * Entries, newest first.
	 *
	 * @return array[]
	 */
	public static function get() {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? array_values( array_filter( $entries, 'is_array' ) ) : array();
	}

	/**
	 * Human label of an action.
	 *
	 * @param string $action ACTION_* constant.
	 * @return string
	 */
	public static function action_label( $action ) {
		$labels = array(
			self::ACTION_GLOBAL_CODE     => __( 'Global code changed', 'advanced-header-footer-code-manager' ),
			self::ACTION_GLOBAL_SETTINGS => __( 'Settings changed', 'advanced-header-footer-code-manager' ),
			self::ACTION_GLOBAL_RESTORE  => __( 'Global code restored', 'advanced-header-footer-code-manager' ),
			self::ACTION_PAGE_CODE       => __( 'Page code changed', 'advanced-header-footer-code-manager' ),
			self::ACTION_PAGE_RESTORE    => __( 'Page code restored', 'advanced-header-footer-code-manager' ),
			self::ACTION_PAGE_CLEARED    => __( 'Page code removed', 'advanced-header-footer-code-manager' ),
		);
		return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
	}

	/**
	 * Describes what changed between two sets of normalized slots, e.g.
	 * "Header: CSS, JS · Footer: turned off".
	 *
	 * @param array $old Old slots (Settings::SLOTS keys).
	 * @param array $new New slots.
	 * @return string
	 */
	public static function describe_slots( array $old, array $new ) {
		$names = array(
			'head'   => __( 'Header', 'advanced-header-footer-code-manager' ),
			'footer' => __( 'Footer', 'advanced-header-footer-code-manager' ),
		);
		$parts = array(
			'css'  => 'CSS',
			'html' => 'HTML',
			'js'   => 'JS',
		);

		$out = array();
		foreach ( Settings::SLOTS as $slot ) {
			$a       = isset( $old[ $slot ] ) ? $old[ $slot ] : array();
			$b       = isset( $new[ $slot ] ) ? $new[ $slot ] : array();
			$changes = array();
			foreach ( $parts as $part => $label ) {
				if ( ( isset( $a[ $part ] ) ? $a[ $part ] : '' ) !== ( isset( $b[ $part ] ) ? $b[ $part ] : '' ) ) {
					$changes[] = $label;
				}
			}
			$was_on = ! isset( $a['enabled'] ) || $a['enabled'];
			$is_on  = ! isset( $b['enabled'] ) || $b['enabled'];
			if ( $was_on !== $is_on ) {
				$changes[] = $is_on ? __( 'turned on', 'advanced-header-footer-code-manager' ) : __( 'turned off', 'advanced-header-footer-code-manager' );
			}
			foreach ( array( 'placement', 'priority' ) as $key ) {
				if ( isset( $a[ $key ], $b[ $key ] ) && $a[ $key ] !== $b[ $key ] ) {
					$changes[] = __( 'position', 'advanced-header-footer-code-manager' );
					break;
				}
			}
			if ( $changes ) {
				$out[] = $names[ $slot ] . ': ' . implode( ', ', $changes );
			}
		}
		return implode( ' · ', $out );
	}
}
