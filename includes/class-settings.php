<?php
/**
 * Global (site-wide) settings stored with the Options API.
 *
 * Option `ahfcm_global_settings` (autoloaded, read on every front-end request):
 *
 *     version                  int    Schema version.
 *     enabled                  bool   Master switch for global code.
 *     slots[head|footer]
 *         code                 string Raw code, stored exactly as typed.
 *         enabled              bool
 *         language             string html|css|javascript
 *         priority             int    Hook priority.
 *     editor_theme             string dark|light
 *     editor_height            int    Pixels.
 *     post_types               array|null  Post types with the per-post box; null = all public.
 *     run_in_builder_preview   bool
 *     output_markers           bool   Wrap output in HTML comments.
 *     delete_data_on_uninstall bool
 *
 * Option `ahfcm_global_history` (not autoloaded) keeps the last few versions of
 * the global code for one-click recovery.
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Global settings.
 */
final class Settings {

	const OPTION         = 'ahfcm_global_settings';
	const HISTORY_OPTION = 'ahfcm_global_history';
	const GROUP          = 'ahfcm_settings';
	const SCHEMA_VERSION = 1;
	const HISTORY_LIMIT  = 5;

	const SLOTS     = array( 'head', 'footer' );
	const LANGUAGES = array( 'html', 'css', 'javascript' );

	/**
	 * Request-level cache of the normalized option.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( __CLASS__, 'option_page_capability' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'record_history' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
		add_action( 'delete_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Registers the option with the Settings API.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * options.php checks this capability before saving our group.
	 *
	 * @return string
	 */
	public static function option_page_capability() {
		return Security::CAP_GLOBAL;
	}

	/**
	 * Default value for one code slot.
	 *
	 * @param string $slot Slot name.
	 * @return array
	 */
	public static function slot_defaults( $slot ) {
		$priorities = array(
			'head'   => 10,
			'footer' => 100,
		);

		return array(
			'code'     => '',
			'enabled'  => true,
			'language' => 'html',
			'priority' => isset( $priorities[ $slot ] ) ? $priorities[ $slot ] : 10,
		);
	}

	/**
	 * Full default option value.
	 *
	 * @return array
	 */
	public static function defaults() {
		$slots = array();
		foreach ( self::SLOTS as $slot ) {
			$slots[ $slot ] = self::slot_defaults( $slot );
		}

		return array(
			'version'                  => self::SCHEMA_VERSION,
			'enabled'                  => true,
			'slots'                    => $slots,
			'editor_theme'             => 'dark',
			'editor_height'            => 320,
			'post_types'               => null,
			'run_in_builder_preview'   => false,
			'output_markers'           => true,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Returns the normalized settings.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null === self::$cache ) {
			$raw         = get_option( self::OPTION, array() );
			self::$cache = self::normalize( is_array( $raw ) ? $raw : array() );
		}
		return self::$cache;
	}

	/**
	 * Clears the request cache.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Settings API sanitize callback.
	 *
	 * Capability and nonce checks happen in options.php (see
	 * option_page_capability()). This callback only validates structure so
	 * it is also safe during activation and WP-CLI updates.
	 *
	 * @param mixed $input Submitted value (already unslashed by options.php).
	 * @return array
	 */
	public static function sanitize( $input ) {
		return self::normalize( is_array( $input ) ? $input : array() );
	}

	/**
	 * Builds a complete, valid settings array from untrusted input.
	 *
	 * Code is kept byte-for-byte (only normalized to a string); everything else
	 * is validated against an allow-list. Missing keys fall back to defaults.
	 *
	 * @param array $raw Raw value.
	 * @return array
	 */
	public static function normalize( array $raw ) {
		$defaults = self::defaults();
		$out      = $defaults;

		$out['enabled'] = self::to_bool( isset( $raw['enabled'] ) ? $raw['enabled'] : $defaults['enabled'] );

		foreach ( self::SLOTS as $slot ) {
			$given                = isset( $raw['slots'][ $slot ] ) && is_array( $raw['slots'][ $slot ] ) ? $raw['slots'][ $slot ] : array();
			$out['slots'][ $slot ] = self::normalize_slot( $given, self::slot_defaults( $slot ), true );
		}

		$out['editor_theme'] = ( isset( $raw['editor_theme'] ) && in_array( $raw['editor_theme'], array( 'dark', 'light' ), true ) ) ? $raw['editor_theme'] : $defaults['editor_theme'];

		$height               = isset( $raw['editor_height'] ) ? absint( $raw['editor_height'] ) : $defaults['editor_height'];
		$out['editor_height'] = max( 120, min( 2000, $height ) );

		if ( isset( $raw['post_types'] ) && is_array( $raw['post_types'] ) ) {
			$out['post_types'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $raw['post_types'] ) ) ) );
		}


		foreach ( array( 'run_in_builder_preview', 'output_markers', 'delete_data_on_uninstall' ) as $flag ) {
			$out[ $flag ] = self::to_bool( isset( $raw[ $flag ] ) ? $raw[ $flag ] : $defaults[ $flag ] );
		}

		return $out;
	}

	/**
	 * Normalizes one slot. Shared with per-post settings.
	 *
	 * @param array $raw           Raw slot.
	 * @param array $defaults      Defaults for the slot.
	 * @param bool  $with_priority Whether the slot carries a hook priority.
	 * @return array
	 */
	public static function normalize_slot( array $raw, array $defaults, $with_priority = false ) {
		$slot = array(
			'code'     => isset( $raw['code'] ) && is_scalar( $raw['code'] ) ? self::normalize_code( (string) $raw['code'] ) : $defaults['code'],
			'enabled'  => self::to_bool( isset( $raw['enabled'] ) ? $raw['enabled'] : $defaults['enabled'] ),
			'language' => ( isset( $raw['language'] ) && in_array( $raw['language'], self::LANGUAGES, true ) ) ? $raw['language'] : $defaults['language'],
		);

		if ( $with_priority ) {
			$priority         = isset( $raw['priority'] ) && is_numeric( $raw['priority'] ) ? (int) $raw['priority'] : $defaults['priority'];
			$slot['priority'] = max( -9999, min( 9999, $priority ) );
		}

		return $slot;
	}

	/**
	 * Normalizes line endings only; code is otherwise untouched.
	 *
	 * Browsers submit textareas with CRLF. Converting to LF keeps storage
	 * stable so "unchanged" saves really are unchanged.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public static function normalize_code( $code ) {
		return str_replace( array( "\r\n", "\r" ), "\n", $code );
	}

	/**
	 * Loose boolean conversion for form and JSON input.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function to_bool( $value ) {
		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( '1', 'true', 'on', 'yes' ), true );
		}
		return (bool) $value;
	}

	/**
	 * Saves the previous global code to the history option when code changes.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $new_value New value.
	 * @return void
	 */
	public function record_history( $old_value, $new_value ) {
		$old = self::normalize( is_array( $old_value ) ? $old_value : array() );
		$new = self::normalize( is_array( $new_value ) ? $new_value : array() );

		if ( self::code_fingerprint( $old ) === self::code_fingerprint( $new ) || ! self::has_code( $old ) ) {
			return;
		}

		$history = self::get_history();
		array_unshift(
			$history,
			array(
				'time'  => time(),
				'user'  => get_current_user_id(),
				'value' => $old,
			)
		);
		update_option( self::HISTORY_OPTION, array_slice( $history, 0, self::HISTORY_LIMIT ), false );
	}

	/**
	 * Returns saved history entries, newest first.
	 *
	 * @return array
	 */
	public static function get_history() {
		$history = get_option( self::HISTORY_OPTION, array() );
		return is_array( $history ) ? array_values( array_filter( $history, 'is_array' ) ) : array();
	}

	/**
	 * Whether any slot contains code.
	 *
	 * @param array $settings Normalized settings.
	 * @return bool
	 */
	public static function has_code( array $settings ) {
		foreach ( self::SLOTS as $slot ) {
			if ( '' !== trim( $settings['slots'][ $slot ]['code'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Hash of everything that affects output (code, toggles, language).
	 *
	 * @param array $settings Normalized settings.
	 * @return string
	 */
	private static function code_fingerprint( array $settings ) {
		return md5( wp_json_encode( array( $settings['enabled'], $settings['slots'] ) ) );
	}
}
