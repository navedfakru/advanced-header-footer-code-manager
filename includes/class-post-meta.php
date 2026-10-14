<?php
/**
 * Per-post code stored in post meta.
 *
 * Meta key `_ahfcm_settings` (protected, single) holds:
 *
 *     version          int
 *     enabled          bool  Master switch for this post's code.
 *     suppress_global  bool  Skip global code on this post.
 *     slots[head|footer] { html, css, js, enabled }
 *
 * The meta is not exposed through the REST API. It is saved by the meta box,
 * which works in both the Classic and the block editor (the block editor
 * posts legacy meta boxes to post.php after its REST save).
 *
 * @package AHFCM
 */

namespace AHFCM;

defined( 'ABSPATH' ) || exit;

/**
 * Post meta storage.
 */
final class Post_Meta {

	const META_KEY     = '_ahfcm_settings';
	const NONCE_FIELD  = '_ahfcm_nonce';
	const FORM_FIELD   = 'ahfcm';
	const NONCE_PREFIX = 'ahfcm_save_post_';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		// Late so custom post types registered on init are included.
		add_action( 'init', array( $this, 'register_meta' ), 99 );
		add_action( 'save_post', array( $this, 'save_from_request' ), 10, 2 );
	}

	/**
	 * Post types that get per-post code.
	 *
	 * @return string[]
	 */
	public static function supported_post_types() {
		$public   = get_post_types( array( 'public' => true ), 'names' );
		$selected = Settings::get()['post_types'];

		$types = null === $selected ? array_diff( $public, array( 'attachment' ) ) : array_intersect( $selected, $public );

		// Breakdance templates, headers, footers and popups (not public types).
		$types = array_merge( array_values( $types ), Breakdance::enabled_template_types() );

		/**
		 * Filters post types that support per-post header/footer code.
		 *
		 * @param string[] $types Post type names.
		 */
		$types = apply_filters( 'ahfcm_post_types', array_values( $types ) );

		return array_values( array_filter( (array) $types, 'post_type_exists' ) );
	}

	/**
	 * Registers the meta key for each supported post type.
	 *
	 * @return void
	 */
	public function register_meta() {
		foreach ( self::supported_post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'object',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
					'auth_callback'     => array( __CLASS__, 'auth_meta' ),
					'revisions_enabled' => post_type_supports( $post_type, 'revisions' ),
					'description'       => __( 'Header, body and footer code for this post.', 'advanced-header-footer-code-manager' ),
				)
			);
		}
	}

	/**
	 * Meta sanitize callback. Normalizes structure; leaves code intact.
	 *
	 * @param mixed $value Meta value.
	 * @return array
	 */
	public static function sanitize_meta( $value ) {
		return self::normalize( is_array( $value ) ? $value : array() );
	}

	/**
	 * Meta auth callback used by core metadata APIs.
	 *
	 * @param bool   $allowed  Whether allowed so far.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public static function auth_meta( $allowed, $meta_key, $post_id, $user_id ) {
		return user_can( $user_id, Security::CAP_POST, $post_id );
	}

	/**
	 * Default per-post value.
	 *
	 * @return array
	 */
	public static function defaults() {
		$slots = array();
		foreach ( Settings::SLOTS as $slot ) {
			$slots[ $slot ] = array(
				'html'    => '',
				'css'     => '',
				'js'      => '',
				'enabled' => true,
			);
		}

		return array(
			'version'         => Settings::SCHEMA_VERSION,
			'enabled'         => true,
			'suppress_global' => false,
			'slots'           => $slots,
		);
	}

	/**
	 * Builds a complete, valid per-post array from untrusted input.
	 *
	 * @param array $raw Raw value.
	 * @return array
	 */
	public static function normalize( array $raw ) {
		$defaults = self::defaults();
		$out      = $defaults;

		$out['enabled']         = Settings::to_bool( isset( $raw['enabled'] ) ? $raw['enabled'] : $defaults['enabled'] );
		$out['suppress_global'] = Settings::to_bool( isset( $raw['suppress_global'] ) ? $raw['suppress_global'] : $defaults['suppress_global'] );

		foreach ( Settings::SLOTS as $slot ) {
			$given                 = isset( $raw['slots'][ $slot ] ) && is_array( $raw['slots'][ $slot ] ) ? $raw['slots'][ $slot ] : array();
			$out['slots'][ $slot ] = Settings::normalize_slot( $given, $defaults['slots'][ $slot ] );
		}

		return $out;
	}

	/**
	 * Returns a post's normalized settings (defaults when none saved).
	 *
	 * Uses the meta cache primed by the main query, so no extra query runs on
	 * singular front-end requests.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get( $post_id ) {
		$raw = get_post_meta( (int) $post_id, self::META_KEY, true );
		return self::normalize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Whether the value carries anything worth storing.
	 *
	 * @param array $data Normalized value.
	 * @return bool
	 */
	public static function is_empty( array $data ) {
		if ( $data['suppress_global'] || ! $data['enabled'] ) {
			return false;
		}
		foreach ( Settings::SLOTS as $slot ) {
			if ( Settings::slot_has_code( $data['slots'][ $slot ] ) || ! $data['slots'][ $slot ]['enabled'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Saves an already-normalized value (or deletes it when empty).
	 *
	 * Core unslashes meta values before storing them, so the value is slashed
	 * first to keep backslashes in code (e.g. JavaScript regexes) intact.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Value.
	 * @return void
	 */
	public static function save( $post_id, array $data ) {
		$data = self::normalize( $data );

		if ( self::is_empty( $data ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		update_post_meta( $post_id, self::META_KEY, wp_slash( $data ) );
	}

	/**
	 * Saves the meta box submission.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_from_request( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ], $_POST[ self::FORM_FIELD ] ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_PREFIX . $post_id ) ) {
			return;
		}

		if ( ! Security::can_edit_post_code( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::supported_post_types(), true ) ) {
			return;
		}

		// Raw code is intentional: only users with unfiltered_html reach this point.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalized below.
		$raw = wp_unslash( $_POST[ self::FORM_FIELD ] );

		// Ignore forms rendered before a data-format upgrade (see Settings::sanitize()).
		if ( ! is_array( $raw ) || ! isset( $raw['_schema'] ) || (int) $raw['_schema'] !== Settings::SCHEMA_VERSION ) {
			return;
		}

		self::save( $post_id, is_array( $raw ) ? $raw : array() );
	}
}
