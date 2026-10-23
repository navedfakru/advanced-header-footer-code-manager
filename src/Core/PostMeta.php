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
 * Meta key `_ahfcm_history` (protected, single) keeps the previous
 * HISTORY_LIMIT versions, newest first: { time, user, value }. Every change is
 * also recorded in the change log (ChangeLog).
 *
 * The meta is not exposed through WordPress' REST API. It is saved by the
 * meta box (Classic editor), and by the editor panel through the plugin's own
 * REST routes (block editor, Breakdance, Oxygen).
 *
 * @package AHFCM
 */

namespace AHFCM\Core;

use AHFCM\Integration\Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Post meta storage.
 */
final class PostMeta {

	const META_KEY     = '_ahfcm_settings';
	const HISTORY_META = '_ahfcm_history';
	const NONCE_FIELD  = '_ahfcm_nonce';
	const FORM_FIELD   = 'ahfcm';
	const NONCE_PREFIX = 'ahfcm_save_post_';

	const HISTORY_LIMIT = 10;

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

		// Builder templates, headers, footers, popups and blocks (not public types).
		$types = array_merge( array_values( $types ), Loader::enabled_template_types() );

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
				'html'      => '',
				'css'       => '',
				'js'        => '',
				'enabled'   => true,
				'placement' => Settings::PLACEMENT_INHERIT,
				'priority'  => Settings::DEFAULT_PRIORITY,
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
			if ( Settings::slot_has_code( $data['slots'][ $slot ] ) || ! $data['slots'][ $slot ]['enabled'] || Settings::PLACEMENT_INHERIT !== $data['slots'][ $slot ]['placement'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Saves a value (or deletes it when empty). The previous version goes to
	 * the post's history and the change is logged.
	 *
	 * Core unslashes meta values before storing them, so the value is slashed
	 * first to keep backslashes in code (e.g. JavaScript regexes) intact.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Value.
	 * @return void
	 */
	public static function save( $post_id, array $data ) {
		$post_id   = (int) $post_id;
		$data      = self::normalize( $data );
		$old       = self::get( $post_id );
		$restoring = ChangeLog::take_restore();
		$changed   = $old !== $data;

		if ( $changed && ! self::is_empty( $old ) ) {
			self::push_history( $post_id, $old );
		}

		if ( self::is_empty( $data ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, wp_slash( $data ) );
		}

		if ( ! $changed ) {
			return;
		}
		if ( $restoring ) {
			$action = ChangeLog::ACTION_PAGE_RESTORE;
		} elseif ( self::is_empty( $data ) ) {
			$action = ChangeLog::ACTION_PAGE_CLEARED;
		} else {
			$action = ChangeLog::ACTION_PAGE_CODE;
		}
		ChangeLog::add( $action, $post_id, self::describe_change( $old, $data ) );
	}

	/**
	 * What changed between two versions of a post's code.
	 *
	 * @param array $old Normalized value.
	 * @param array $new Normalized value.
	 * @return string
	 */
	private static function describe_change( array $old, array $new ) {
		$details = array();
		if ( $old['enabled'] !== $new['enabled'] ) {
			$details[] = $new['enabled'] ? __( 'Page code turned on', 'advanced-header-footer-code-manager' ) : __( 'Page code turned off', 'advanced-header-footer-code-manager' );
		}
		if ( $old['suppress_global'] !== $new['suppress_global'] ) {
			$details[] = $new['suppress_global'] ? __( 'Global code skipped here', 'advanced-header-footer-code-manager' ) : __( 'Global code loaded again', 'advanced-header-footer-code-manager' );
		}
		$slots = ChangeLog::describe_slots( $old['slots'], $new['slots'] );
		if ( '' !== $slots ) {
			$details[] = $slots;
		}
		return implode( ' · ', $details );
	}

	/**
	 * Adds a version to the front of a post's history.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $value   Normalized value.
	 * @return void
	 */
	private static function push_history( $post_id, array $value ) {
		$history = self::get_history( $post_id );
		array_unshift(
			$history,
			array(
				'time'  => time(),
				'user'  => get_current_user_id(),
				'value' => $value,
			)
		);
		update_post_meta( $post_id, self::HISTORY_META, wp_slash( array_slice( $history, 0, self::HISTORY_LIMIT ) ) );
	}

	/**
	 * A post's earlier versions, newest first.
	 *
	 * @param int $post_id Post ID.
	 * @return array[]
	 */
	public static function get_history( $post_id ) {
		$history = get_post_meta( (int) $post_id, self::HISTORY_META, true );
		return is_array( $history ) ? array_values( array_filter( $history, 'is_array' ) ) : array();
	}

	/**
	 * Restores an earlier version. The version it replaces goes to the history.
	 *
	 * @param int $post_id Post ID.
	 * @param int $index   History index (0 = newest).
	 * @return bool Whether that version existed.
	 */
	public static function restore( $post_id, $index ) {
		$history = self::get_history( $post_id );
		if ( ! isset( $history[ $index ]['value'] ) || ! is_array( $history[ $index ]['value'] ) ) {
			return false;
		}
		ChangeLog::mark_restore();
		self::save( $post_id, $history[ $index ]['value'] );
		return true;
	}

	/**
	 * Posts that have earlier versions, most recently changed first.
	 *
	 * @param int $limit Maximum number of posts.
	 * @return int[]
	 */
	public static function posts_with_history( $limit = 50 ) {
		$ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin tools screen only.
				'meta_key'       => self::HISTORY_META,
			)
		);
		$ids = array_merge( $ids, self::template_posts_with_history() );

		$times = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$history = self::get_history( $id );
			if ( $history ) {
				$times[ $id ] = (int) $history[0]['time'];
			}
		}
		arsort( $times );
		return array_slice( array_keys( $times ), 0, $limit );
	}

	/**
	 * Builder documents (not public, so missed by post_type "any") with history.
	 *
	 * @return int[]
	 */
	private static function template_posts_with_history() {
		$types = Loader::enabled_template_types();
		if ( ! $types ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin tools screen only.
				'meta_key'       => self::HISTORY_META,
			)
		);
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
