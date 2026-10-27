<?php
/**
 * Global (site-wide) settings stored with the Options API.
 *
 * Option `ahfcm_global_settings` (autoloaded, read on every front-end request):
 *
 *     version                  int    Schema version.
 *     enabled                  bool   Master switch for global code.
 *     slots[head|footer]
 *         html                 string Raw HTML, stored exactly as typed.
 *         css                  string Raw CSS (output inside <style>).
 *         js                   string Raw JavaScript (output inside <script>).
 *         enabled              bool
 *         placement            string priority (use `priority`) | end (last in
 *                                     wp_head / wp_footer, right before </head> / </body>).
 *         priority             int    Hook priority when placement is `priority`.
 *     (Schema 1–2 stored one `code` + `language`; normalize_slot() converts it on read.)
 *
 *     editor_theme             string dark|light
 *     editor_height            int    Pixels.
 *     post_types               array|null  Post types with the per-post box; null = all public.
 *     breakdance_types         array|null  Builder (Breakdance/Oxygen) template types with their own code; null = all.
 *     integrations             array  Integration name => enabled (missing = enabled).
 *     run_in_builder_preview   bool
 *     builder_button           bool   Show the button in the Breakdance builder.
 *     editor_button            bool   Show the button in the block editor toolbar.
 *     builder_button_roles     array|null  Roles that see it; null = every role allowed to edit code.
 *     builder_button_hide_users int[] Users who never see it.
 *     output_markers           bool   Wrap output in HTML comments.
 *     editors_page_code        bool   Users without manage_options (editors) may edit
 *                                     page code, if they also have unfiltered_html.
 *     delete_data_on_uninstall bool
 *
 * Option `ahfcm_global_history` (not autoloaded) keeps the last HISTORY_LIMIT
 * versions of the global code for one-click recovery. Every change is also
 * recorded in the change log (ChangeLog).
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Global settings.
 */
final class Settings {

	const OPTION         = 'ahfcm_global_settings';
	const HISTORY_OPTION = 'ahfcm_global_history';
	const GROUP          = 'ahfcm_settings';
	const SCHEMA_VERSION = 4;

	/**
	 * Default hook priority: right after page-builder assets.
	 *
	 * Breakdance prints its CSS/JS through a placeholder at priority 1000000
	 * (BREAKDANCE_ASSETS_PRIORITY) in both wp_head and wp_footer, so code must
	 * come later for CSS overrides to win and for scripts to see the builder's
	 * libraries. Also a sensible "end of head / end of body" for other themes.
	 */
	const DEFAULT_PRIORITY = 1000001;

	/** Allowed priority range. */
	const MAX_PRIORITY = 99999999;

	/**
	 * Placement of a code slot.
	 *
	 * PLACEMENT_PRIORITY: printed at the slot's own hook priority.
	 * PLACEMENT_END:      printed last in wp_head / wp_footer (END_PRIORITY),
	 *                     i.e. right before </head> / </body> in themes and
	 *                     builders that call wp_head() / wp_footer() there.
	 * PLACEMENT_INHERIT:  per-page code only; follows the global slot.
	 */
	const PLACEMENT_PRIORITY = 'priority';
	const PLACEMENT_END      = 'end';
	const PLACEMENT_INHERIT  = 'inherit';

	/** Hook priority used for PLACEMENT_END. */
	const END_PRIORITY = PHP_INT_MAX;

	const HISTORY_LIMIT = 10;

	const SLOTS = array( 'head', 'footer' );

	/** Code parts of a slot, in output order. */
	const PARTS = array( 'css', 'html', 'js' );

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
		add_action( 'add_option_' . self::OPTION, array( $this, 'record_first_save' ), 10, 2 );
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
			'head'   => self::DEFAULT_PRIORITY,
			'footer' => self::DEFAULT_PRIORITY,
		);

		return array(
			'html'     => '',
			'css'      => '',
			'js'       => '',
			'enabled'   => true,
			'placement' => self::PLACEMENT_PRIORITY,
			'priority'  => isset( $priorities[ $slot ] ) ? $priorities[ $slot ] : self::DEFAULT_PRIORITY,
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
			'breakdance_types'         => null,
			'run_in_builder_preview'   => false,
			'builder_button'           => true,
			'editor_button'            => true,
			'builder_button_roles'     => null,
			'builder_button_hide_users' => array(),
			'integrations'             => array(),
			'output_markers'           => true,
			'editors_page_code'        => false,
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
		// A settings page opened before a data-format upgrade would post the
		// old field layout and wipe code. Refuse such saves and keep what is
		// stored. Only the options.php form carries `_schema`; programmatic
		// update_option() calls are not affected.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php already verified the nonce.
		$is_form = isset( $_POST['option_page'] ) && self::GROUP === $_POST['option_page'];

		// update_option() passes an already-sanitized value back through this
		// callback when the stored value equals the registered default (it falls
		// through to add_option()). That value carries `version`, not `_schema`,
		// so accept it; a stale form posts neither and is still refused.
		$has_schema        = is_array( $input ) && isset( $input['_schema'] ) && (int) $input['_schema'] === self::SCHEMA_VERSION;
		$already_sanitized = is_array( $input ) && ! isset( $input['_schema'] ) && isset( $input['version'] ) && (int) $input['version'] === self::SCHEMA_VERSION;

		if ( $is_form && ! $has_schema && ! $already_sanitized ) {
			add_settings_error(
				self::OPTION,
				'ahfcm_stale_form',
				__( 'Not saved: this page was opened before the plugin was updated. Reload the page and make your changes again.', 'advanced-header-footer-code-manager' )
			);
			$current = get_option( self::OPTION, array() );
			return self::normalize( is_array( $current ) ? $current : array() );
		}

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

		foreach ( array( 'post_types', 'breakdance_types', 'builder_button_roles' ) as $list ) {
			if ( isset( $raw[ $list ] ) && is_array( $raw[ $list ] ) ) {
				$out[ $list ] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $raw[ $list ] ) ) ) );
			}
		}

		$out['builder_button_hide_users'] = isset( $raw['builder_button_hide_users'] ) && is_array( $raw['builder_button_hide_users'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $raw['builder_button_hide_users'] ) ) ) )
			: $defaults['builder_button_hide_users'];

		if ( isset( $raw['integrations'] ) && is_array( $raw['integrations'] ) ) {
			foreach ( $raw['integrations'] as $name => $on ) {
				$out['integrations'][ sanitize_key( $name ) ] = self::to_bool( $on );
			}
		}

		foreach ( array( 'run_in_builder_preview', 'output_markers', 'delete_data_on_uninstall', 'builder_button', 'editor_button', 'editors_page_code' ) as $flag ) {
			$out[ $flag ] = self::to_bool( isset( $raw[ $flag ] ) ? $raw[ $flag ] : $defaults[ $flag ] );
		}

		return $out;
	}

	/**
	 * Hook priority a slot prints at.
	 *
	 * @param array      $slot        Normalized slot (global or per-page).
	 * @param array|null $global_slot Global slot of the same location, used
	 *                                when `$slot` follows the global placement.
	 * @return int
	 */
	public static function slot_priority( array $slot, $global_slot = null ) {
		$placement = isset( $slot['placement'] ) ? $slot['placement'] : self::PLACEMENT_INHERIT;
		if ( self::PLACEMENT_INHERIT === $placement ) {
			$slot      = is_array( $global_slot ) ? $global_slot : array();
			$placement = isset( $slot['placement'] ) ? $slot['placement'] : self::PLACEMENT_PRIORITY;
		}
		if ( self::PLACEMENT_END === $placement ) {
			return self::END_PRIORITY;
		}
		return isset( $slot['priority'] ) ? (int) $slot['priority'] : self::DEFAULT_PRIORITY;
	}

	/**
	 * Whether an integration is switched on (on unless explicitly turned off).
	 *
	 * @param string $name Integration name, e.g. breakdance.
	 * @return bool
	 */
	public static function integration_enabled( $name ) {
		$list = self::get()['integrations'];
		return ! isset( $list[ $name ] ) || $list[ $name ];
	}

	/**
	 * Normalizes one slot. Shared with per-post settings.
	 *
	 * `placement` and `priority` are kept when the defaults carry them.
	 *
	 * @param array $raw           Raw slot.
	 * @param array $defaults      Defaults for the slot.
	 * @param bool  $with_priority Always keep a hook priority.
	 * @return array
	 */
	public static function normalize_slot( array $raw, array $defaults, $with_priority = false ) {
		$slot = array();
		foreach ( array( 'html', 'css', 'js' ) as $part ) {
			$slot[ $part ] = isset( $raw[ $part ] ) && is_scalar( $raw[ $part ] ) ? self::normalize_code( (string) $raw[ $part ] ) : $defaults[ $part ];
		}

		// Legacy shape (one `code` + `language`): move the code to its part.
		$has_parts = isset( $raw['html'] ) || isset( $raw['css'] ) || isset( $raw['js'] );
		if ( ! $has_parts && isset( $raw['code'] ) && is_scalar( $raw['code'] ) ) {
			$map           = array(
				'css'        => 'css',
				'javascript' => 'js',
				'js'         => 'js',
			);
			$language      = isset( $raw['language'] ) && isset( $map[ $raw['language'] ] ) ? $map[ $raw['language'] ] : 'html';
			$slot[ $language ] = self::normalize_code( (string) $raw['code'] );
		}

		$slot['enabled'] = self::to_bool( isset( $raw['enabled'] ) ? $raw['enabled'] : $defaults['enabled'] );

		if ( isset( $defaults['placement'] ) ) {
			// Per-page slots may follow the global slot; global slots may not.
			$allowed           = self::PLACEMENT_INHERIT === $defaults['placement']
				? array( self::PLACEMENT_INHERIT, self::PLACEMENT_PRIORITY, self::PLACEMENT_END )
				: array( self::PLACEMENT_PRIORITY, self::PLACEMENT_END );
			$slot['placement'] = isset( $raw['placement'] ) && in_array( $raw['placement'], $allowed, true ) ? $raw['placement'] : $defaults['placement'];
		}

		if ( $with_priority || isset( $defaults['priority'] ) ) {
			$priority         = isset( $raw['priority'] ) && is_numeric( $raw['priority'] ) ? (int) $raw['priority'] : $defaults['priority'];
			$slot['priority'] = max( -self::MAX_PRIORITY, min( self::MAX_PRIORITY, $priority ) );
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
	 * When global settings change: keeps the previous code in the history
	 * (when code changed) and records the change in the change log.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $new_value New value.
	 * @return void
	 */
	public function record_history( $old_value, $new_value ) {
		$old       = self::normalize( is_array( $old_value ) ? $old_value : array() );
		$new       = self::normalize( is_array( $new_value ) ? $new_value : array() );
		$restoring = ChangeLog::take_restore();

		if ( self::code_fingerprint( $old ) !== self::code_fingerprint( $new ) && self::has_code( $old ) ) {
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

		$details = self::describe_code_change( $old, $new );
		if ( '' !== $details ) {
			ChangeLog::add( $restoring ? ChangeLog::ACTION_GLOBAL_RESTORE : ChangeLog::ACTION_GLOBAL_CODE, 0, $details );
		}

		$changed = array();
		foreach ( array_keys( self::defaults() ) as $key ) {
			if ( ! in_array( $key, array( 'version', 'enabled', 'slots' ), true ) && $old[ $key ] !== $new[ $key ] ) {
				$changed[] = self::setting_label( $key, $new[ $key ] );
			}
		}
		if ( $changed ) {
			ChangeLog::add( ChangeLog::ACTION_GLOBAL_SETTINGS, 0, implode( ', ', $changed ) );
		}
	}

	/**
	 * Logs global code saved for the first time (the option did not exist yet).
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 * @return void
	 */
	public function record_first_save( $option, $value ) {
		$details = self::describe_code_change( self::defaults(), self::normalize( is_array( $value ) ? $value : array() ) );
		if ( '' !== $details ) {
			ChangeLog::add( ChangeLog::ACTION_GLOBAL_CODE, 0, $details );
		}
	}

	/**
	 * What changed in the global code (master switch + slots), or ''.
	 *
	 * @param array $old Normalized settings.
	 * @param array $new Normalized settings.
	 * @return string
	 */
	private static function describe_code_change( array $old, array $new ) {
		$details = array();
		if ( $old['enabled'] !== $new['enabled'] ) {
			$details[] = $new['enabled'] ? __( 'Global code turned on', 'advanced-header-footer-code-manager' ) : __( 'Global code turned off', 'advanced-header-footer-code-manager' );
		}
		$slots = ChangeLog::describe_slots( $old['slots'], $new['slots'] );
		if ( '' !== $slots ) {
			$details[] = $slots;
		}
		return implode( ' · ', $details );
	}

	/**
	 * Change-log label of a setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value New value.
	 * @return string
	 */
	private static function setting_label( $key, $value ) {
		if ( 'editors_page_code' === $key ) {
			return $value
				? __( 'Editors can now edit page code', 'advanced-header-footer-code-manager' )
				: __( 'Only administrators can edit page code', 'advanced-header-footer-code-manager' );
		}
		$label = ucfirst( str_replace( '_', ' ', $key ) );
		return is_bool( $value ) ? $label . ': ' . ( $value ? __( 'on', 'advanced-header-footer-code-manager' ) : __( 'off', 'advanced-header-footer-code-manager' ) ) : $label;
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
			if ( self::slot_has_code( $settings['slots'][ $slot ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether one slot has any non-blank code part.
	 *
	 * @param array $slot Normalized slot.
	 * @return bool
	 */
	public static function slot_has_code( array $slot ) {
		foreach ( self::PARTS as $part ) {
			if ( isset( $slot[ $part ] ) && '' !== trim( $slot[ $part ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Total size of a slot's code in bytes.
	 *
	 * @param array $slot Slot (any schema).
	 * @return int
	 */
	public static function slot_size( array $slot ) {
		$slot = self::normalize_slot( $slot, self::slot_defaults( 'head' ) );
		return strlen( $slot['html'] ) + strlen( $slot['css'] ) + strlen( $slot['js'] );
	}

	/**
	 * Hash of everything that affects output (code, toggles, language).
	 *
	 * @param array $settings Normalized settings.
	 * @return string
	 */
	private static function code_fingerprint( array $settings ) {
		$slots = array();
		foreach ( $settings['slots'] as $key => $slot ) {
			unset( $slot['priority'], $slot['placement'] );
			$slots[ $key ] = $slot;
		}
		return md5( wp_json_encode( array( $settings['enabled'], $slots ) ) );
	}
}
