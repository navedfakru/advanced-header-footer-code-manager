<?php
/**
 * Integration registry: knows every integration, registers the active and
 * enabled ones, and answers questions the core asks across all of them.
 *
 * @package AHFCM
 */

namespace AHFCM\Integration;

use AHFCM\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Integration loader.
 */
final class Loader {

	/**
	 * Integration instances keyed by name.
	 *
	 * @var IntegrationInterface[]|null
	 */
	private static $integrations = null;

	/**
	 * Every known integration, keyed by name.
	 *
	 * @return IntegrationInterface[]
	 */
	public static function all() {
		if ( null === self::$integrations ) {
			$list = array(
				new Gutenberg\Main(),
				new Breakdance\Main(),
				new Oxygen\Main(),
			);

			/**
			 * Filters the integrations. Add an IntegrationInterface to support
			 * another editor or builder.
			 *
			 * @param IntegrationInterface[] $list Integrations.
			 */
			$list = apply_filters( 'ahfcm_integrations', $list );

			self::$integrations = array();
			foreach ( (array) $list as $integration ) {
				if ( $integration instanceof IntegrationInterface ) {
					self::$integrations[ $integration->get_name() ] = $integration;
				}
			}
		}
		return self::$integrations;
	}

	/**
	 * One integration by name.
	 *
	 * @param string $name Name.
	 * @return IntegrationInterface|null
	 */
	public static function get( $name ) {
		$all = self::all();
		return isset( $all[ $name ] ) ? $all[ $name ] : null;
	}

	/**
	 * Integrations that are installed and switched on in Settings.
	 *
	 * @return IntegrationInterface[]
	 */
	public static function running() {
		return array_filter(
			self::all(),
			static function ( IntegrationInterface $integration ) {
				return $integration->is_active() && Settings::integration_enabled( $integration->get_name() );
			}
		);
	}

	/**
	 * Hooks every running integration (on plugins_loaded, after builders load).
	 *
	 * @return void
	 */
	public static function register() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		foreach ( self::running() as $integration ) {
			$integration->register();
		}
	}

	/**
	 * Template-like post types offered by running integrations.
	 *
	 * @return array<string,string> Post type => label.
	 */
	public static function template_types() {
		$types = array();
		foreach ( self::running() as $integration ) {
			$types += $integration->get_template_types();
		}
		return $types;
	}

	/**
	 * Template types switched on in Settings (all by default).
	 *
	 * @return string[]
	 */
	public static function enabled_template_types() {
		$available = array_keys( self::template_types() );
		$selected  = Settings::get()['breakdance_types'];
		return null === $selected ? $available : array_values( array_intersect( $available, $selected ) );
	}

	/**
	 * Whether a post type belongs to a builder integration.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_template_type( $post_type ) {
		return array_key_exists( (string) $post_type, self::template_types() );
	}

	/**
	 * Builder documents whose code applies to the current request.
	 *
	 * @param int    $page_id Singular post being viewed (0 if none).
	 * @param string $slot    head|footer.
	 * @return int[]
	 */
	public static function documents_for_request( $page_id, $slot ) {
		$ids = array();
		foreach ( self::running() as $integration ) {
			$ids = array_merge( $ids, $integration->get_documents_for_request( (int) $page_id, $slot ) );
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * The running Breakdance-engine builder (Breakdance or Oxygen), if any.
	 *
	 * @return Breakdance\Main|null
	 */
	public static function builder() {
		foreach ( self::running() as $integration ) {
			if ( $integration instanceof Breakdance\Main ) {
				return $integration;
			}
		}
		return null;
	}

	/**
	 * Clears cached instances (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		foreach ( (array) self::$integrations as $integration ) {
			if ( $integration instanceof Breakdance\Main ) {
				$integration->flush_cache();
			}
		}
	}
}
